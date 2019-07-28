<?php

namespace Bellona\Cookie;

use Bellona\Support\ServiceProvider;

use Bellona\Cookie\Cookie;

class CookieServiceProvider extends ServiceProvider
{
    public $defer = true;

    public $services = [Cookie::class];

    public function register()
    {
        $this->app->singleton(Cookie::class, function ($app) {
            return new Cookie;
        });
    }
}
