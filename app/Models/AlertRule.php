<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $latency_limit_ms
 * @property string $error_rate_limit
 * @property bool $notify_email
 * @property bool $notify_slack
 * @property bool $notify_webhook
 * @property string|null $slack_webhook_url
 * @property string|null $webhook_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['latency_limit_ms', 'error_rate_limit', 'notify_email', 'notify_slack', 'notify_webhook', 'slack_webhook_url', 'webhook_url'])]
class AlertRule extends Model
{
    /** Defaults for users that never saved their rules. */
    protected $attributes = [
        'latency_limit_ms' => 500,
        'error_rate_limit' => 1.00,
        'notify_email' => true,
        'notify_slack' => false,
        'notify_webhook' => false,
    ];

    protected function casts(): array
    {
        return [
            'latency_limit_ms' => 'integer',
            'error_rate_limit' => 'decimal:2',
            'notify_email' => 'boolean',
            'notify_slack' => 'boolean',
            'notify_webhook' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
