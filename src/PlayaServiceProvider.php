<?php

namespace CharlieLangridge\Playa;

use CharlieLangridge\Playa\Commands\PrunePlayersCommand;
use CharlieLangridge\Playa\Http\Middleware\EnsurePlayer;
use CharlieLangridge\Playa\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class PlayaServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('playa')
            ->hasConfigFile()
            ->hasMigration('create_playa_players_table')
            ->hasCommand(PrunePlayersCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(Playa::class);
    }

    public function packageBooted(): void
    {
        $this->app->make(Router::class)->aliasMiddleware('playa', EnsurePlayer::class);

        Request::macro('player', function (): ?Player {
            $player = $this->attributes->get('playa.player');

            return $player instanceof Player ? $player : null;
        });
    }
}
