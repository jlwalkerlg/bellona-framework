<?php

namespace Bellona\Support\Facades;

use Bellona\Support\Facades\Facade;
use Bellona\Http\Request as RequestService;

class Request extends Facade
{
    protected static $service = RequestService::class;
}
