<?php

namespace Bellona\Support\Facades;

use Bellona\Support\Facades\Facade;
use Bellona\Http\Router as RouterService;

class Router extends Facade
{
    protected static $service = RouterService::class;
}
