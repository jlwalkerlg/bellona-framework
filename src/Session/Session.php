<?php

namespace Bellona\Session;

class Session
{
    public function __construct(SessionContract $driver)
    {
        $this->driver = $driver;
    }


    public function __call($name, $arguments)
    {
        return $this->driver->$name(...$arguments);
    }
}
