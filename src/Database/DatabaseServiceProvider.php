<?php

namespace Bellona\Database;

use Bellona\Support\ServiceProvider;
use Bellona\Database\Database;
use Bellona\Database\QueryBuilder;

class DatabaseServiceProvider extends ServiceProvider
{
    public $defer = true;

    public $services = [Database::class, QueryBuilder::class];

    public function register()
    {
        $this->app->singleton(Database::class, function($app) {
            return new Database;
        });

        $this->app->bind(QueryBuilder::class, function($app) {
            $dbh = $app[Database::class]->connection();
            return new QueryBuilder($dbh);
        });
    }
}
