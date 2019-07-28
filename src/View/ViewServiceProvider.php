<?php

namespace Bellona\View;

use Bellona\Support\ServiceProvider;
use Bellona\View\ViewFactory;
use Bellona\Session\Session;

class ViewServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(ViewFactory::class, function($app) {
            return new ViewFactory($app[Session::class]);
        });
    }
}
