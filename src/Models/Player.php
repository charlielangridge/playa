<?php

namespace CharlieLangridge\Playa\Models;

use CharlieLangridge\Playa\Database\Factories\PlayerFactory;
use CharlieLangridge\Playa\Events\PlayerLinkedToUser;
use CharlieLangridge\Playa\IdentityPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $uuid
 * @property int|string|null $user_id
 * @property IdentityPolicy $persistence_policy
 * @property Carbon|null $expires_at
 */
class Player extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'uuid',
        'user_id',
        'name',
        'username',
        'persistence_policy',
        'data',
        'last_seen_at',
        'expires_at',
    ];

    public function getTable(): string
    {
        return (string) config('playa.table_name', 'playa_players');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function isExpired(): bool
    {
        if (! $this->expires_at) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    public function linkUser(Model $user): self
    {
        if ((string) $this->user_id === (string) $user->getKey()) {
            return $this;
        }

        $this->forceFill([
            'user_id' => $user->getKey(),
        ])->save();

        PlayerLinkedToUser::dispatch($this, $user);

        return $this;
    }

    public function unlinkUser(): self
    {
        $this->forceFill([
            'user_id' => null,
        ])->save();

        return $this;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo($this->userModel(), 'user_id');
    }

    public function scopeExpired(Builder $query, ?Carbon $before = null): Builder
    {
        return $query
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $before ?? Carbon::now());
    }

    public function scopeActive(Builder $query, ?Carbon $at = null): Builder
    {
        $at ??= Carbon::now();

        return $query->where(function (Builder $query) use ($at): void {
            $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', $at);
        });
    }

    protected static function newFactory(): PlayerFactory
    {
        return PlayerFactory::new();
    }

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'persistence_policy' => IdentityPolicy::class,
            'last_seen_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected function userModel(): string
    {
        $userModel = config('playa.user_model')
            ?? config('auth.providers.users.model');

        return is_string($userModel) ? $userModel : 'App\\Models\\User';
    }
}
