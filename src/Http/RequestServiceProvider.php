<?php

namespace Bellona\Http;

use Bellona\Support\ServiceProvider;
use Bellona\Http\Request;
use Bellona\Session\Session;

class RequestServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(Request::class, function ($app) {
            return new Request($app[Session::class]);
        });
    }
}
