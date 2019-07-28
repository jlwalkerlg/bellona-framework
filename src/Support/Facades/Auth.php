<?php

namespace Bellona\Support\Facades;

use Bellona\Support\Facades\Facade;
use Bellona\Auth\Authentication;

class Auth extends Facade
{
    protected static $service = Authentication::class;
}
