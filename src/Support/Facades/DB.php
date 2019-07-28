<?php

namespace Bellona\Support\Facades;

use Bellona\Support\Facades\Facade;
use Bellona\Database\QueryBuilder;

class DB extends Facade
{
    protected static $service = QueryBuilder::class;
}
