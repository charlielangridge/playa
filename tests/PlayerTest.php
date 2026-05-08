<?php

use CharlieLangridge\Playa\Events\PlayerLinkedToUser;
use CharlieLangridge\Playa\Models\Player;
use CharlieLangridge\Playa\Tests\Support\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('creates the expected players table', function () {
    expect(Schema::hasTable('playa_players'))->toBeTrue()
        ->and(Schema::hasColumns('playa_players', [
            'id',
            'uuid',
            'user_id',
            'name',
            'username',
            'data',
            'last_seen_at',
            'expires_at',
            'created_at',
            'updated_at',
        ]))->toBeTrue();
});

it('uses uuid as the public route key while keeping an incrementing id', function () {
    $player = Player::factory()->create();

    expect($player->id)->toBeInt()
        ->and(Str::isUuid($player->uuid))->toBeTrue()
        ->and($player->getRouteKeyName())->toBe('uuid')
        ->and($player->getRouteKey())->toBe($player->uuid);
});

it('casts profile data and expiry timestamps', function () {
    $player = Player::factory()->create([
        'name' => 'Charlie',
        'username' => 'charlie',
        'data' => ['score' => 10],
    ]);

    expect($player->fresh()->data)->toBe(['score' => 10])
        ->and($player->fresh()->last_seen_at)->not->toBeNull()
        ->and($player->fresh()->expires_at)->not->toBeNull()
        ->and($player->fresh()->isExpired())->toBeFalse();
});

it('links and unlinks a player to a user manually', function () {
    Event::fake([PlayerLinkedToUser::class]);

    $user = User::create(['name' => 'Charlie']);
    $player = Player::factory()->create(['user_id' => null]);

    $player->linkUser($user);

    expect($player->fresh()->user->is($user))->toBeTrue();

    Event::assertDispatched(
        PlayerLinkedToUser::class,
        fn (PlayerLinkedToUser $event): bool => $event->player->is($player) && $event->user->is($user),
    );

    $player->unlinkUser();

    expect($player->fresh()->user_id)->toBeNull();
});
