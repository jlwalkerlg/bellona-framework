<?php

namespace Bellona\Security;

use Bellona\Support\ServiceProvider;
use Bellona\Security\CSRF;
use Bellona\Session\Session;
use Bellona\Http\Request;

class CSRFServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(CSRF::class, function ($app) {
            return new CSRF($app[Request::class], $app[Session::class]);
        });
    }
}
