<?php

namespace CharlieLangridge\Playa\Events;

use CharlieLangridge\Playa\Models\Player;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlayerLinkedToUser
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Player $player,
        public Model $user,
    ) {}
}
