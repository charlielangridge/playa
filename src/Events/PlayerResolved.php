<?php

namespace CharlieLangridge\Playa\Events;

use CharlieLangridge\Playa\Models\Player;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlayerResolved
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Player $player) {}
}
