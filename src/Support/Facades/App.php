<?php

namespace Bellona\Support\Facades;

use Bellona\Support\Facades\Facade;
use Bellona\Core\Application;

class App extends Facade
{
    protected static $service = Application::class;
}
