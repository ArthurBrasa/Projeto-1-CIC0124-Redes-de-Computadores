<?php

namespace App\Models;

use App\Events\ExperimentUpdated;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One Network Lab test run.
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $target_url
 * @property array<string, int> $params
 * @property string $status
 * @property int $progress
 * @property array<string, mixed>|null $results
 * @property array<string, mixed>|null $samples
 * @property string|null $error
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon $created_at
 */
#[Fillable(['type', 'target_url', 'params', 'status', 'progress', 'results', 'samples', 'error', 'started_at', 'finished_at'])]
class NetworkExperiment extends Model
{
    public const RTT = 'rtt';
    public const THROUGHPUT = 'throughput';
    public const CONCURRENCY = 'concurrency';

    public const TYPES = [self::RTT, self::THROUGHPUT, self::CONCURRENCY];

    /** Progress and results reach the page live. */
    protected $dispatchesEvents = [
        'saved' => ExperimentUpdated::class,
    ];

    protected function casts(): array
    {
        return [
            'params' => 'array',
            'results' => 'array',
            'samples' => 'array',
            'progress' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRunning(): bool
    {
        return in_array($this->status, ['queued', 'running']);
    }

    public function host(): string
    {
        return parse_url($this->target_url, PHP_URL_HOST) ?: $this->target_url;
    }

    public function paramsLabel(): string
    {
        $p = $this->params;

        return match ($this->type) {
            self::RTT => ($p['samples'] ?? '?').' samples',
            self::THROUGHPUT => ($p['clients'] ?? '?').' clients · '.($p['duration'] ?? '?').'s',
            self::CONCURRENCY => 'up to '.($p['clients'] ?? '?').' clients',
            default => '',
        };
    }

    /** One line with the main result, for the history table. */
    public function resultLabel(): string
    {
        $r = $this->results ?? [];

        if ($this->status === 'failed') {
            return 'failed';
        }
        if (! $r) {
            return '—';
        }

        return match ($this->type) {
            self::RTT => ($r['rtt_avg'] ?? '—').'ms avg RTT · '.($r['loss'] ?? 0).'% loss',
            self::THROUGHPUT => ($r['req_per_sec'] ?? '—').' req/s · '.self::bytes($r['bytes_per_sec'] ?? 0).'/s',
            self::CONCURRENCY => ($r['avg_at_max'] ?? '—').'ms at '.($r['max_clients'] ?? '?').' clients',
            default => '',
        };
    }

    public static function bytes(float|int $b): string
    {
        return $b >= 1048576 ? round($b / 1048576, 1).' MB' : ($b >= 1024 ? round($b / 1024, 1).' KB' : round($b).' B');
    }
}
