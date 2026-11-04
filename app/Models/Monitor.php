<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Support\AlertManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $url
 * @property string $method
 * @property int $interval_seconds
 * @property int $timeout_seconds
 * @property int $expected_status
 * @property bool $is_active
 * @property bool $is_public
 * @property Carbon|null $last_checked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'url', 'method', 'interval_seconds', 'timeout_seconds', 'expected_status', 'is_active', 'is_public'])]
class Monitor extends Model
{
    public const METHODS = ['GET', 'POST'];

    /** seconds => label */
    public const INTERVALS = [30 => '30 seconds', 60 => '1 minute', 300 => '5 minutes', 900 => '15 minutes'];

    public const TIMEOUTS = [5 => '5 seconds', 10 => '10 seconds', 30 => '30 seconds'];

    protected $attributes = [
        'method' => 'GET',
        'interval_seconds' => 60,
        'timeout_seconds' => 10,
        'expected_status' => 200,
        'is_active' => true,
        'is_public' => false,
    ];

    protected function casts(): array
    {
        return [
            'interval_seconds' => 'integer',
            'timeout_seconds' => 'integer',
            'expected_status' => 'integer',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'last_checked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // alerts point here polymorphically (no foreign key), so remove them by hand
        static::deleting(fn (Monitor $monitor) => $monitor->alerts()->delete());
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphMany<Alert, $this> */
    public function alerts(): MorphMany
    {
        return $this->morphMany(Alert::class, 'alertable');
    }

    /** @return HasMany<MonitorCheck, $this> */
    public function checks(): HasMany
    {
        return $this->hasMany(MonitorCheck::class);
    }

    /** @return HasOne<MonitorCheck, $this> */
    public function latestCheck(): HasOne
    {
        return $this->hasOne(MonitorCheck::class)->latestOfMany('checked_at');
    }

    public function intervalLabel(): string
    {
        return self::INTERVALS[$this->interval_seconds] ?? $this->interval_seconds.'s';
    }

    /** Active and its interval has passed since the last check. */
    public function isDue(): bool
    {
        return $this->is_active
            && (! $this->last_checked_at || $this->last_checked_at->lte(now()->subSeconds($this->interval_seconds - 5)));
    }

    /** Request the URL once and store the result. */
    public function runCheck(): MonitorCheck
    {
        $started = microtime(true);
        $result = ['is_up' => false, 'status_code' => null, 'response_ms' => null, 'error' => null];

        try {
            $response = Http::timeout($this->timeout_seconds)->send($this->method, $this->url);

            $result['status_code'] = $response->status();
            $result['is_up'] = $response->status() === $this->expected_status;
            $result['error'] = $result['is_up'] ? null : 'Expected '.$this->expected_status.', got '.$response->status();
        } catch (\Throwable $e) {
            // "cURL error 7: Failed to connect ... (see https://curl.se/...) for http://..." -> "Failed to connect ..."
            $message = Str::of($e->getMessage())->before(' (see ')->replaceMatches('/^cURL error \d+: /', '');
            $result['error'] = Str::limit((string) $message, 250);
        }

        $result['response_ms'] = (int) round((microtime(true) - $started) * 1000);
        $result['checked_at'] = now();

        $check = $this->checks()->create($result);
        $this->forceFill(['last_checked_at' => $check->checked_at])->saveQuietly();

        AlertManager::checkMonitor($this, $check);

        return $check;
    }

    /**
     * One entry per day, oldest first: ['date' => Carbon, 'state' => up|degraded|down|none].
     * A day is "degraded" when at least 90% of its checks passed.
     *
     * @return array{days: array<int, array{date: Carbon, state: string}>, uptime: float|null}
     */
    public function history(int $days = 90): array
    {
        // Days follow the display timezone, while checked_at is stored in UTC.
        $from = now(config('app.display_timezone'))->subDays($days - 1)->startOfDay();
        $minutes = (int) $from->utcOffset();

        $day = match ($this->getConnection()->getDriverName()) {
            'sqlite' => "date(checked_at, '{$minutes} minutes')",
            'pgsql' => "date(checked_at + interval '{$minutes} minutes')",
            default => "date(date_add(checked_at, interval {$minutes} minute))",
        };

        $rows = $this->checks()
            ->where('checked_at', '>=', $from->utc())
            ->selectRaw("{$day} as day, count(*) as total, sum(case when is_up then 1 else 0 end) as up")
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $result = [];
        $total = 0;
        $up = 0;

        for ($i = 0; $i < $days; $i++) {
            $date = $from->copy()->addDays($i);
            $row = $rows[$date->toDateString()] ?? null;
            $state = 'none';

            if ($row && $row->total > 0) {
                $ratio = $row->up / $row->total;
                $state = $ratio >= 1 ? 'up' : ($ratio >= 0.9 ? 'degraded' : 'down');
                $total += $row->total;
                $up += $row->up;
            }

            $result[] = ['date' => $date, 'state' => $state];
        }

        return ['days' => $result, 'uptime' => $total ? round($up / $total * 100, 2) : null];
    }
}
