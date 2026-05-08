<?php

namespace charlielangridge\Playa\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \charlielangridge\Playa\Playa
 */
class Playa extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \charlielangridge\Playa\Playa::class;
    }
}
