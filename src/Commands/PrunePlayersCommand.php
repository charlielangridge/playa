<?php

namespace CharlieLangridge\Playa\Commands;

use CharlieLangridge\Playa\Support\PlayerModel;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PrunePlayersCommand extends Command
{
    public $signature = 'playa:prune {--hours=0 : Only prune players that have been expired for this many hours}';

    public $description = 'Delete expired Playa player records';

    public function handle(): int
    {
        $hours = max(0, (int) $this->option('hours'));

        $deleted = PlayerModel::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', Carbon::now()->subHours($hours))
            ->delete();

        $record = $deleted === 1 ? 'record' : 'records';

        $this->comment("Deleted {$deleted} expired player {$record}.");

        return self::SUCCESS;
    }
}
