<?php

namespace CharlieLangridge\Playa\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \CharlieLangridge\Playa\Playa
 */
class Playa extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \CharlieLangridge\Playa\Playa::class;
    }
}
