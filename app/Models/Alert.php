<?php

namespace App\Models;

use App\Events\AlertChanged;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $alertable_type
 * @property int $alertable_id
 * @property string $status
 * @property string $title
 * @property array<string, mixed>|null $details
 * @property Carbon $started_at
 * @property Carbon|null $acknowledged_at
 * @property int|null $acknowledged_by
 * @property string|null $note
 * @property Carbon|null $resolved_at
 * @property-read Monitor|Application|null $alertable
 */
#[Fillable(['user_id', 'type', 'status', 'title', 'details', 'started_at', 'acknowledged_at', 'acknowledged_by', 'note', 'resolved_at'])]
class Alert extends Model
{
    public const MONITOR_DOWN = 'monitor_down';

    public const LATENCY = 'latency';

    public const ERROR_RATE = 'error_rate';

    public const ACTIVE = 'active';

    public const ACKNOWLEDGED = 'acknowledged';

    public const RESOLVED = 'resolved';

    /** Live updates for the alerts page, menu badge... */
    protected $dispatchesEvents = [
        'saved' => AlertChanged::class,
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'started_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function alertable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /** Active or acknowledged: the problem is still happening. */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', [self::ACTIVE, self::ACKNOWLEDGED]);
    }

    public function isOpen(): bool
    {
        return $this->status !== self::RESOLVED;
    }

    public function severity(): string
    {
        return $this->type === self::LATENCY ? 'warning' : 'critical';
    }

    public function typeLabel(): string
    {
        return ['monitor_down' => 'DOWN', 'latency' => 'LATENCY', 'error_rate' => 'ERROR RATE'][$this->type] ?? strtoupper($this->type);
    }

    /** Human description of the latest numbers. */
    public function summary(): string
    {
        $d = $this->details ?? [];

        return match ($this->type) {
            self::MONITOR_DOWN => $d['error'] ?? 'Not responding',
            self::LATENCY => ($d['response_ms'] ?? '?').'ms (limit '.($d['limit'] ?? '?').'ms)',
            self::ERROR_RATE => ($d['rate'] ?? '?').'% errors, '.($d['errors'] ?? 0).' of '.($d['total'] ?? 0).' events (limit '.($d['limit'] ?? '?').'%)',
            default => '',
        };
    }

    /** Page of the monitor / application this alert is about. */
    public function subjectUrl(): ?string
    {
        return match (true) {
            $this->alertable instanceof Monitor => route('monitors.show', $this->alertable),
            $this->alertable instanceof Application => route('applications.show', $this->alertable),
            default => null,
        };
    }

    public function duration(): string
    {
        return $this->started_at->diffForHumans($this->resolved_at ?? now(), true);
    }
}
