<?php

namespace CharlieLangridge\Playa\Support;

use CharlieLangridge\Playa\Models\Player;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class PlayerModel
{
    public static function className(): string
    {
        $playerModel = config('playa.player_model');

        if (! is_string($playerModel) || $playerModel === '') {
            return Player::class;
        }

        if ($playerModel === Player::class || is_subclass_of($playerModel, Player::class)) {
            return $playerModel;
        }

        throw new InvalidArgumentException('The configured Playa player model must extend '.Player::class.'.');
    }

    public static function query(): Builder
    {
        $playerModel = self::className();

        return $playerModel::query();
    }
}
