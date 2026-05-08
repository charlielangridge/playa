<?php

namespace charlielangridge\Playa\Commands;

use Illuminate\Console\Command;

class PlayaCommand extends Command
{
    public $signature = 'playa';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
