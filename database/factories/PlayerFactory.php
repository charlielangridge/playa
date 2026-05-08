<?php

namespace CharlieLangridge\Playa\Database\Factories;

use CharlieLangridge\Playa\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class PlayerFactory extends Factory
{
    protected $model = Player::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'username' => $this->faker->unique()->userName(),
            'data' => [],
            'last_seen_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addDays(30),
        ];
    }

    public function expired(): self
    {
        return $this->state(fn (): array => [
            'expires_at' => Carbon::now()->subMinute(),
        ]);
    }
}
