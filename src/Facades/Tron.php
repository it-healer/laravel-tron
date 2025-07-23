<?php

namespace ItHealer\LaravelTron\Facades;

use Illuminate\Support\Facades\Facade;

class Tron extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \ItHealer\LaravelTron\Tron::class;
    }
}
