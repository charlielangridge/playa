<?php

use CharlieLangridge\Playa\Models\Player;

it('prunes expired players without deleting active players', function () {
    $expiredPlayers = Player::factory()
        ->count(2)
        ->expired()
        ->create();

    $activePlayer = Player::factory()->create();
    $persistentPlayer = Player::factory()->create(['expires_at' => null]);

    $this
        ->artisan('playa:prune')
        ->expectsOutput('Deleted 2 expired player records.')
        ->assertSuccessful();

    expect(Player::query()->whereKey($expiredPlayers->pluck('id'))->exists())->toBeFalse()
        ->and(Player::query()->whereKey($activePlayer->id)->exists())->toBeTrue()
        ->and(Player::query()->whereKey($persistentPlayer->id)->exists())->toBeTrue();
});
