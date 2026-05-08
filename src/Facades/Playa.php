<?php

namespace CharlieLangridge\Playa\Facades;

use CharlieLangridge\Playa\Playa as PlayaManager;
use Illuminate\Support\Facades\Facade;

/**
 * @see PlayaManager
 */
class Playa extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PlayaManager::class;
    }
}
