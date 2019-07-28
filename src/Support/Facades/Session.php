<?php

namespace Bellona\Support\Facades;

use Bellona\Support\Facades\Facade;
use Bellona\Session\Session as SessionService;

class Session extends Facade
{
    protected static $service = SessionService::class;
}
