<?php

namespace Bellona\Support\Facades;

use Bellona\Support\Facades\Facade;
use Bellona\Security\CSRF as CSRFService;

class CSRF extends Facade
{
    protected static $service = CSRFService::class;
}
