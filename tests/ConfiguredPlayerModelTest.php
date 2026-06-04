<?php

use CharlieLangridge\Playa\Events\PlayerCreated;
use CharlieLangridge\Playa\Events\PlayerResolved;
use CharlieLangridge\Playa\Facades\Playa;
use CharlieLangridge\Playa\Tests\Support\CustomPlayer;
use CharlieLangridge\Playa\Tests\Support\InvalidPlayerModel;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

it('creates and finds players using the configured player model', function () {
    config()->set('playa.player_model', CustomPlayer::class);

    $player = Playa::create(['username' => 'configured-player']);
    $foundPlayer = Playa::findByUuid($player->uuid);

    expect($player)->toBeInstanceOf(CustomPlayer::class)
        ->and($player->marker())->toBe('custom-player')
        ->and($foundPlayer)->toBeInstanceOf(CustomPlayer::class)
        ->and($foundPlayer?->is($player))->toBeTrue();
});

it('resolves the current player as the configured player model', function () {
    config()->set('playa.player_model', CustomPlayer::class);

    Event::fake([PlayerCreated::class, PlayerResolved::class]);

    Route::get('configured-player-model', function (Request $request) {
        $requestPlayer = $request->player();
        $facadePlayer = Playa::player();

        return response()->json([
            'request_class' => $requestPlayer::class,
            'facade_class' => $facadePlayer::class,
            'found_class' => Playa::findByUuid($requestPlayer->uuid)::class,
        ]);
    })->middleware(['web', 'playa']);

    $this
        ->get('configured-player-model')
        ->assertOk()
        ->assertJson([
            'request_class' => CustomPlayer::class,
            'facade_class' => CustomPlayer::class,
            'found_class' => CustomPlayer::class,
        ]);

    Event::assertDispatched(
        PlayerCreated::class,
        fn (PlayerCreated $event): bool => $event->player instanceof CustomPlayer,
    );

    Event::assertDispatched(
        PlayerResolved::class,
        fn (PlayerResolved $event): bool => $event->player instanceof CustomPlayer,
    );
});

it('prunes expired players using the configured player model', function () {
    config()->set('playa.player_model', CustomPlayer::class);

    $expiredPlayer = Playa::create(['expires_at' => Carbon::now()->subMinute()]);
    $activePlayer = Playa::create(['expires_at' => Carbon::now()->addDay()]);

    $this
        ->artisan('playa:prune')
        ->expectsOutput('Deleted 1 expired player record.')
        ->assertSuccessful();

    expect(CustomPlayer::query()->whereKey($expiredPlayer->id)->exists())->toBeFalse()
        ->and(CustomPlayer::query()->whereKey($activePlayer->id)->exists())->toBeTrue();
});

it('rejects configured player models that do not extend the package player model', function () {
    config()->set('playa.player_model', InvalidPlayerModel::class);

    Playa::create();
})->throws(
    InvalidArgumentException::class,
    'The configured Playa player model must extend CharlieLangridge\Playa\Models\Player.',
);
