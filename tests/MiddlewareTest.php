<?php

use CharlieLangridge\Playa\Events\PlayerCreated;
use CharlieLangridge\Playa\Events\PlayerExpired;
use CharlieLangridge\Playa\Events\PlayerRenewed;
use CharlieLangridge\Playa\Events\PlayerResolved;
use CharlieLangridge\Playa\Facades\Playa;
use CharlieLangridge\Playa\IdentityPolicy;
use CharlieLangridge\Playa\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

function registerPlayerRoute(string $uri = 'playa-test'): void
{
    Route::get($uri, function (Request $request) {
        return response()->json([
            'request_uuid' => $request->player()?->uuid,
            'facade_uuid' => Playa::player()?->uuid,
        ]);
    })->middleware(['web', 'playa']);
}

it('creates a player and stores its uuid in a cookie when no cookie exists', function () {
    Event::fake([PlayerCreated::class, PlayerResolved::class]);

    registerPlayerRoute();

    $response = $this->get('playa-test');

    $response
        ->assertOk()
        ->assertCookie('playa_player');

    $uuid = $response->json('request_uuid');

    expect($uuid)->toBeString()
        ->and($response->json('facade_uuid'))->toBe($uuid)
        ->and(Player::query()->where('uuid', $uuid)->exists())->toBeTrue();

    Event::assertDispatched(PlayerCreated::class);
    Event::assertDispatched(PlayerResolved::class);
});

it('resolves and renews an existing player from a valid cookie', function () {
    Event::fake([PlayerResolved::class, PlayerRenewed::class]);

    $this->travelTo(Carbon::parse('2026-01-01 12:00:00'));

    $player = Player::factory()->create([
        'last_seen_at' => Carbon::now()->subDay(),
        'expires_at' => Carbon::now()->addDay(),
    ]);

    registerPlayerRoute();

    $response = $this
        ->withCookie('playa_player', $player->uuid)
        ->get('playa-test');

    $response
        ->assertOk()
        ->assertJson([
            'request_uuid' => $player->uuid,
            'facade_uuid' => $player->uuid,
        ])
        ->assertCookie('playa_player');

    expect(Player::query()->count())->toBe(1)
        ->and($player->fresh()->expires_at->toDateTimeString())->toBe(Carbon::now()->addDays(30)->toDateTimeString())
        ->and($player->fresh()->last_seen_at->toDateTimeString())->toBe(Carbon::now()->toDateTimeString());

    Event::assertDispatched(PlayerResolved::class);
    Event::assertDispatched(PlayerRenewed::class);
});

it('treats players from a pre-migration schema as rolling identities', function () {
    $player = Player::factory()->create();
    Schema::table('playa_players', function ($table): void {
        $table->dropIndex(['persistence_policy']);
        $table->dropColumn('persistence_policy');
    });
    registerPlayerRoute();

    $this->withCookie('playa_player', $player->uuid)
        ->get('playa-test')
        ->assertOk()
        ->assertJsonPath('request_uuid', $player->uuid)
        ->assertCookie('playa_player');
});

it('replaces invalid player cookies with a new player', function () {
    registerPlayerRoute();

    $response = $this
        ->withCookie('playa_player', 'not-a-uuid')
        ->get('playa-test');

    $uuid = $response->json('request_uuid');

    $response
        ->assertOk()
        ->assertCookie('playa_player');

    expect($uuid)->toBeString()
        ->and(Player::query()->where('uuid', $uuid)->exists())->toBeTrue()
        ->and(Player::query()->count())->toBe(1);
});

it('replaces expired players and dispatches the expired event', function () {
    Event::fake([PlayerExpired::class, PlayerCreated::class]);

    $expiredPlayer = Player::factory()->expired()->create();

    registerPlayerRoute();

    $response = $this
        ->withCookie('playa_player', $expiredPlayer->uuid)
        ->get('playa-test');

    $uuid = $response->json('request_uuid');

    expect($uuid)->not->toBe($expiredPlayer->uuid)
        ->and(Player::query()->count())->toBe(2)
        ->and(Player::query()->where('uuid', $uuid)->exists())->toBeTrue();

    Event::assertDispatched(PlayerExpired::class);
    Event::assertDispatched(PlayerCreated::class);
});

it('can keep the original expiry when renewal is disabled', function () {
    Event::fake([PlayerRenewed::class]);

    config()->set('playa.renew_on_visit', false);

    $this->travelTo(Carbon::parse('2026-01-01 12:00:00'));

    $expiresAt = Carbon::now()->addDays(10);

    $player = Player::factory()->create([
        'last_seen_at' => Carbon::now()->subDay(),
        'expires_at' => $expiresAt,
    ]);

    registerPlayerRoute();

    $response = $this
        ->withCookie('playa_player', $player->uuid)
        ->get('playa-test')
        ->assertOk();

    $cookie = collect($response->headers->getCookies())
        ->first(fn ($cookie): bool => $cookie->getName() === 'playa_player');

    expect($player->fresh()->expires_at->toDateTimeString())->toBe($expiresAt->toDateTimeString())
        ->and($player->fresh()->last_seen_at->toDateTimeString())->toBe(Carbon::now()->toDateTimeString())
        ->and(Carbon::createFromTimestamp($cookie->getExpiresTime())->toDateTimeString())->toBe($expiresAt->toDateTimeString());

    Event::assertNotDispatched(PlayerRenewed::class);
});

it('uses the configured cookie attributes', function () {
    config()->set('playa.cookie.name', 'game_player');
    config()->set('playa.cookie.path', '/games');
    config()->set('playa.cookie.secure', true);
    config()->set('playa.cookie.http_only', true);
    config()->set('playa.cookie.same_site', 'strict');

    registerPlayerRoute();

    $response = $this->get('playa-test');

    $cookie = collect($response->headers->getCookies())
        ->first(fn ($cookie): bool => $cookie->getName() === 'game_player');

    expect($cookie)->not->toBeNull()
        ->and($cookie->getPath())->toBe('/games')
        ->and($cookie->isSecure())->toBeTrue()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getSameSite())->toBe('strict');
});

it('can forget the current player cookie through the facade', function () {
    $player = Player::factory()->create();

    Route::get('playa-forget', function () {
        Playa::forget();

        return response('forgotten');
    })->middleware(['web', 'playa']);

    $response = $this
        ->withCookie('playa_player', $player->uuid)
        ->get('playa-forget');

    $cookie = collect($response->headers->getCookies())
        ->first(fn ($cookie): bool => $cookie->getName() === 'playa_player');

    expect($cookie)->not->toBeNull()
        ->and($cookie->isCleared())->toBeTrue();
});

it('finds an existing active player without mutating or renewing it', function () {
    Event::fake([PlayerResolved::class, PlayerRenewed::class]);
    $this->travelTo(Carbon::parse('2026-01-01 12:00:00'));
    $player = Player::factory()->create([
        'last_seen_at' => Carbon::now()->subDay(),
        'expires_at' => Carbon::now()->addDay(),
    ]);
    $request = Request::create('/join');
    $request->cookies->set('playa_player', $player->uuid);

    $resolved = Playa::findExisting($request);

    expect($resolved?->is($player))->toBeTrue()
        ->and($player->fresh()->last_seen_at->toDateTimeString())->toBe(Carbon::now()->subDay()->toDateTimeString())
        ->and($player->fresh()->expires_at->toDateTimeString())->toBe(Carbon::now()->addDay()->toDateTimeString())
        ->and(Playa::player())->toBeNull()
        ->and($request->attributes->has('playa.player'))->toBeFalse();
    Event::assertNotDispatched(PlayerResolved::class);
    Event::assertNotDispatched(PlayerRenewed::class);
});

it('returns null when finding an expired existing player', function () {
    $player = Player::factory()->expired()->create();
    $request = Request::create('/join');
    $request->cookies->set('playa_player', $player->uuid);

    expect(Playa::findExisting($request))->toBeNull();
});

it('creates a session identity with a browser session cookie and fixed expiry', function () {
    $this->travelTo(Carbon::parse('2026-01-01 12:00:00'));
    config()->set('playa.policies.session.lifetime_minutes', 60 * 24);
    $request = Request::create('/join');

    $player = Playa::resolve($request, IdentityPolicy::Session);
    $cookie = Playa::cookieFor($player);

    expect($player->persistence_policy)->toBe(IdentityPolicy::Session)
        ->and($player->expires_at->toDateTimeString())->toBe('2026-01-02 12:00:00')
        ->and($cookie->getExpiresTime())->toBe(0);
});

it('does not renew a returning session identity', function () {
    Event::fake([PlayerRenewed::class]);
    $this->travelTo(Carbon::parse('2026-01-01 12:00:00'));
    $player = Player::factory()->create([
        'persistence_policy' => IdentityPolicy::Session,
        'expires_at' => Carbon::now()->addHour(),
    ]);
    $request = Request::create('/game');
    $request->cookies->set('playa_player', $player->uuid);

    Playa::resolve($request);

    expect($player->fresh()->expires_at->toDateTimeString())->toBe('2026-01-01 13:00:00');
    Event::assertNotDispatched(PlayerRenewed::class);
});

it('upgrades a session identity to rolling without changing its uuid', function () {
    $this->travelTo(Carbon::parse('2026-01-01 12:00:00'));
    config()->set('playa.policies.rolling.lifetime_minutes', 60 * 24 * 365);
    $player = Player::factory()->create([
        'persistence_policy' => IdentityPolicy::Session,
        'expires_at' => Carbon::now()->addHour(),
    ]);
    $request = Request::create('/join');
    $request->cookies->set('playa_player', $player->uuid);

    $resolved = Playa::resolve($request, IdentityPolicy::Rolling);

    expect($resolved->uuid)->toBe($player->uuid)
        ->and($resolved->persistence_policy)->toBe(IdentityPolicy::Rolling)
        ->and($resolved->expires_at->toDateTimeString())->toBe('2027-01-01 12:00:00')
        ->and(Playa::cookieFor($resolved)->getExpiresTime())->toBeGreaterThan(0);
});

it('does not downgrade a rolling identity when session policy is requested', function () {
    $player = Player::factory()->create(['persistence_policy' => IdentityPolicy::Rolling]);
    $request = Request::create('/join');
    $request->cookies->set('playa_player', $player->uuid);

    $resolved = Playa::resolve($request, IdentityPolicy::Session);

    expect($resolved->persistence_policy)->toBe(IdentityPolicy::Rolling);
});
