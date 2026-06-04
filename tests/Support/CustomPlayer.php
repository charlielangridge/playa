<?php

namespace CharlieLangridge\Playa\Tests\Support;

use CharlieLangridge\Playa\Models\Player;

class CustomPlayer extends Player
{
    public function marker(): string
    {
        return 'custom-player';
    }
}
