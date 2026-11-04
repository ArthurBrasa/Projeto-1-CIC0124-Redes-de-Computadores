<?php

namespace App\Models;

use App\Events\MonitorChecked;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $monitor_id
 * @property bool $is_up
 * @property int|null $status_code
 * @property int|null $response_ms
 * @property string|null $error
 * @property Carbon $checked_at
 */
#[Fillable(['is_up', 'status_code', 'response_ms', 'error', 'checked_at'])]
class MonitorCheck extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'is_up' => 'boolean',
            'status_code' => 'integer',
            'response_ms' => 'integer',
            'checked_at' => 'datetime',
        ];
    }

    protected $dispatchesEvents = [
        'created' => MonitorChecked::class,
    ];

    /** @return BelongsTo<Monitor, $this> */
    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }
}
