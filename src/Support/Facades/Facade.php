<?php

namespace Bellona\Support\Facades;

/**
 * Core Facade class which all Facades should extend.
 *
 * Provides the __callStatic() magic method which takes care
 * of resolving the proxied class and calling the appropriate method.
 */
class Facade
{
    public static function __callStatic($name, $arguments)
    {
        return app(static::$service)->$name(...$arguments);
    }
}
