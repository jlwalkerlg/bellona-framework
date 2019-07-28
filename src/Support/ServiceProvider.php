<?php

namespace Bellona\Support;

abstract class ServiceProvider
{
    protected $app;

    public $defer = false;

    public $services = [];

    public function __construct()
    {
        $this->app = app();
    }

    abstract public function register();
}
