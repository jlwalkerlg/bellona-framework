<?php

namespace Bellona\Support\Facades;

use Bellona\Support\Facades\Facade;

use Bellona\Cookie\Cookie as CookieService;

class Cookie extends Facade
{
    protected static $service = CookieService::class;
}
