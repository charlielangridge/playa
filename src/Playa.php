<?php

namespace CharlieLangridge\Playa;

use CharlieLangridge\Playa\Events\PlayerCreated;
use CharlieLangridge\Playa\Events\PlayerExpired;
use CharlieLangridge\Playa\Events\PlayerRenewed;
use CharlieLangridge\Playa\Events\PlayerResolved;
use CharlieLangridge\Playa\Models\Player;
use CharlieLangridge\Playa\Support\PlayerModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

class Playa
{
    protected ?Player $currentPlayer = null;

    protected bool $forgetQueued = false;

    public function player(): ?Player
    {
        return $this->currentPlayer;
    }

    public function resolve(Request $request, ?IdentityPolicy $requestedPolicy = null): Player
    {
        $uuid = $request->cookies->get($this->cookieName());

        if (! is_string($uuid) || ! Str::isUuid($uuid)) {
            return $this->createForRequest($request, $requestedPolicy ?? $this->defaultPolicy());
        }

        $player = $this->findByUuid($uuid);

        if (! $player) {
            return $this->createForRequest($request, $requestedPolicy ?? $this->defaultPolicy());
        }

        if ($player->isExpired()) {
            PlayerExpired::dispatch($player);

            return $this->createForRequest($request, $requestedPolicy ?? $this->defaultPolicy());
        }

        if ($requestedPolicy === IdentityPolicy::Rolling && $this->policyFor($player) === IdentityPolicy::Session) {
            $player->forceFill([
                'persistence_policy' => IdentityPolicy::Rolling,
                'expires_at' => $this->expiresAt(IdentityPolicy::Rolling),
            ])->save();
        }

        $this->remember($request, $player);
        $this->refresh($player);
        $this->linkAuthenticatedUser($request, $player);

        PlayerResolved::dispatch($player);

        return $player;
    }

    public function create(array $attributes = []): Player
    {
        $policy = $this->policyFrom($attributes['persistence_policy'] ?? null);
        $attributes['persistence_policy'] = $policy;
        $attributes['last_seen_at'] ??= Carbon::now();
        $attributes['expires_at'] ??= $this->expiresAt($policy);

        $player = PlayerModel::query()->create($attributes);

        if (! $player instanceof Player) {
            throw new \RuntimeException('The configured Playa player model must extend '.Player::class.'.');
        }

        PlayerCreated::dispatch($player);

        return $player;
    }

    public function findByUuid(string $uuid): ?Player
    {
        if (! Str::isUuid($uuid)) {
            return null;
        }

        $player = PlayerModel::query()
            ->where('uuid', $uuid)
            ->first();

        return $player instanceof Player ? $player : null;
    }

    public function findExisting(Request $request): ?Player
    {
        $uuid = $request->cookies->get($this->cookieName());

        if (! is_string($uuid)) {
            return null;
        }

        $player = $this->findByUuid($uuid);

        return $player && ! $player->isExpired() ? $player : null;
    }

    public function forget(): SymfonyCookie
    {
        $this->currentPlayer = null;
        $this->forgetQueued = true;

        return $this->forgetCookie();
    }

    public function shouldForgetCurrentPlayer(): bool
    {
        return $this->forgetQueued;
    }

    public function cookieFor(Player $player): SymfonyCookie
    {
        return Cookie::make(
            $this->cookieName(),
            $player->uuid,
            $this->cookieLifetimeMinutes($player),
            $this->cookiePath(),
            $this->cookieDomain(),
            $this->cookieSecure(),
            $this->cookieHttpOnly(),
            false,
            $this->cookieSameSite(),
        );
    }

    public function forgetCookie(): SymfonyCookie
    {
        return Cookie::forget(
            $this->cookieName(),
            $this->cookiePath(),
            $this->cookieDomain(),
        );
    }

    public function cookieName(): string
    {
        return (string) config('playa.cookie.name', 'playa_player');
    }

    protected function createForRequest(Request $request, IdentityPolicy $policy): Player
    {
        $player = $this->create(['persistence_policy' => $policy]);

        $this->remember($request, $player);
        $this->linkAuthenticatedUser($request, $player);

        PlayerResolved::dispatch($player);

        return $player;
    }

    protected function remember(Request $request, Player $player): void
    {
        $this->currentPlayer = $player;
        $this->forgetQueued = false;

        $request->attributes->set('playa.player', $player);
    }

    protected function refresh(Player $player): void
    {
        $attributes = [
            'last_seen_at' => Carbon::now(),
        ];

        $policy = $this->policyFor($player);

        if ($this->renewsOnVisit($policy)) {
            $attributes['expires_at'] = $this->expiresAt($policy);
        }

        $player->forceFill($attributes)->save();

        if ($this->renewsOnVisit($policy)) {
            PlayerRenewed::dispatch($player);
        }
    }

    protected function linkAuthenticatedUser(Request $request, Player $player): void
    {
        if (! (bool) config('playa.auto_link_authenticated_user', false)) {
            return;
        }

        $user = $request->user();

        if (! $user instanceof Model) {
            return;
        }

        $player->linkUser($user);
    }

    protected function expiresAt(IdentityPolicy $policy): ?Carbon
    {
        $lifetime = config(
            'playa.policies.'.$policy->value.'.lifetime_minutes',
            config('playa.lifetime_minutes'),
        );

        if ($lifetime === null) {
            return null;
        }

        return Carbon::now()->addMinutes((int) $lifetime);
    }

    protected function renewsOnVisit(IdentityPolicy $policy): bool
    {
        return (bool) config(
            'playa.policies.'.$policy->value.'.renew_on_visit',
            config('playa.renew_on_visit', true),
        );
    }

    protected function cookieLifetimeMinutes(Player $player): int
    {
        $policy = $this->policyFor($player);
        $configuredLifetime = config(
            'playa.policies.'.$policy->value.'.cookie_lifetime_minutes',
            config('playa.cookie.lifetime_minutes'),
        );

        if ($configuredLifetime !== null) {
            return (int) $configuredLifetime;
        }

        if ($player->expires_at) {
            $secondsUntilExpiry = Carbon::now()->diffInSeconds($player->expires_at, false);

            return max(1, (int) ceil($secondsUntilExpiry / 60));
        }

        return (int) (config(
            'playa.policies.'.$policy->value.'.lifetime_minutes',
            config('playa.lifetime_minutes'),
        ) ?? 0);
    }

    protected function defaultPolicy(): IdentityPolicy
    {
        return $this->policyFrom(config('playa.default_policy', IdentityPolicy::Rolling->value));
    }

    protected function policyFrom(mixed $policy): IdentityPolicy
    {
        if ($policy instanceof IdentityPolicy) {
            return $policy;
        }

        return IdentityPolicy::tryFrom((string) $policy) ?? $this->defaultPolicyFallback();
    }

    protected function policyFor(Player $player): IdentityPolicy
    {
        return $this->policyFrom($player->getAttribute('persistence_policy'));
    }

    protected function defaultPolicyFallback(): IdentityPolicy
    {
        return IdentityPolicy::Rolling;
    }

    protected function cookiePath(): string
    {
        return (string) config('playa.cookie.path', '/');
    }

    protected function cookieDomain(): ?string
    {
        $domain = config('playa.cookie.domain');

        return is_string($domain) ? $domain : null;
    }

    protected function cookieSecure(): ?bool
    {
        $secure = config('playa.cookie.secure');

        return is_bool($secure) ? $secure : null;
    }

    protected function cookieHttpOnly(): bool
    {
        return (bool) config('playa.cookie.http_only', true);
    }

    protected function cookieSameSite(): ?string
    {
        $sameSite = config('playa.cookie.same_site', 'lax');

        return is_string($sameSite) ? $sameSite : null;
    }
}
