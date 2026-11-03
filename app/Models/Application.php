<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property string $public_id
 * @property string $name
 * @property string|null $description
 * @property string $environment
 * @property string $url
 * @property string|null $token_hash
 * @property string|null $token_prefix
 * @property Carbon|null $token_rotated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'description', 'environment', 'url'])]
#[Hidden(['token_hash'])]
class Application extends Model
{
    public const ENVIRONMENTS = ['production', 'staging', 'development'];

    protected function casts(): array
    {
        return [
            'token_rotated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Application $app) {
            $app->public_id ??= 'app_'.Str::lower(Str::random(10));
        });
    }

    /** URLs use the public id: /applications/app_xxxxxxxxxx */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<ApplicationEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ApplicationEvent::class);
    }

    public function tokenMatches(?string $token): bool
    {
        return $token && $this->token_hash && hash_equals($this->token_hash, hash('sha256', $token));
    }

    /**
     * Create a new API token, store only its hash and return the plain value.
     * The plain token can't be recovered later, so show it to the user once.
     */
    public function generateToken(): string
    {
        $token = 'pm_'.Str::random(40);

        $this->forceFill([
            'token_hash' => hash('sha256', $token),
            'token_prefix' => Str::substr($token, 0, 10),
            'token_rotated_at' => now(),
        ])->save();

        return $token;
    }

    public static function findByToken(string $token): ?self
    {
        return static::where('token_hash', hash('sha256', $token))->first();
    }

    public function maskedToken(): string
    {
        return $this->token_prefix ? $this->token_prefix.' ••••••••' : '—';
    }

    public function webhookUrl(): string
    {
        return url('/api/v1/hooks/'.$this->public_id);
    }
}
