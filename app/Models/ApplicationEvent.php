<?php

namespace App\Models;

use App\Events\ApplicationEventReceived;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $application_id
 * @property string $level
 * @property string $message
 * @property string|null $source
 * @property array<string, mixed>|null $payload
 * @property Carbon $created_at
 */
#[Fillable(['level', 'message', 'source', 'payload'])]
class ApplicationEvent extends Model
{
    public const LEVELS = ['info', 'warning', 'error'];

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected $dispatchesEvents = [
        'created' => ApplicationEventReceived::class,
    ];

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
