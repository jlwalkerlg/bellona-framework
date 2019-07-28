<?php

namespace Bellona\Support\Facades;

use Bellona\Support\Facades\Facade;
use Bellona\View\ViewFactory;

class View extends Facade
{
    protected static $service = ViewFactory::class;
}
