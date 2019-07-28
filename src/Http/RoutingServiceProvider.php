<?php

namespace Bellona\Http;

use Bellona\Support\ServiceProvider;
use Bellona\Http\Router;
use Bellona\Session\Session;

class RoutingServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(Router::class, function($app) {
            return new Router($app, $app[Request::class], $app[Session::class]);
        });
    }
}
