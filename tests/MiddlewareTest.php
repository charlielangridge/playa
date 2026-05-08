<?php

use CharlieLangridge\Playa\Events\PlayerCreated;
use CharlieLangridge\Playa\Events\PlayerExpired;
use CharlieLangridge\Playa\Events\PlayerRenewed;
use CharlieLangridge\Playa\Events\PlayerResolved;
use CharlieLangridge\Playa\Facades\Playa;
use CharlieLangridge\Playa\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

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
