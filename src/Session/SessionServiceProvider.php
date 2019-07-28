<?php

namespace Bellona\Session;

use Bellona\Support\ServiceProvider;
use Bellona\Cookie\Cookie;
use Bellona\Encryption\Encryptor;

class SessionServiceProvider extends ServiceProvider
{
    public function register()
    {
        require_once PROJECT_ROOT . '/config/session.php';

        $this->app->singleton(Session::class, function ($app) {
            switch (SESSION_DRIVER) {
                case 'cookie':
                    $cookie = $app[Cookie::class];
                    $encryptor = $app[Encryptor::class];
                    $driver = new CookieSessionDriver($cookie, $encryptor);
                    break;
                case 'session':
                default:
                    $driver = new SessionSessionDriver;
            }
            return new Session($driver);
        });
    }
}
