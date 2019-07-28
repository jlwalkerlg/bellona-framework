<?php

namespace Bellona\Auth;

use Bellona\Auth\AuthContract;

class Authentication
{
    public function __construct(AuthContract $driver)
    {
        $this->driver = $driver;
    }


    public function __call($name, $arguments)
    {
        return $this->driver->$name(...$arguments);
    }
}
