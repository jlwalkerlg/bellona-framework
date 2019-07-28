<?php

namespace Bellona\Auth;

use Bellona\Support\ServiceProvider;
use Bellona\Auth\Authentication;
use Bellona\Auth\SessionAuthDriver;
use Bellona\Auth\Authorization;
use Bellona\Session\Session;
use Bellona\Cookie\Cookie;
use Bellona\Encryption\Encryptor;

class AuthServiceProvider extends ServiceProvider
{
    public $defer = true;

    public $services = [Authentication::class, Authorization::class];

    public function register()
    {
        require_once PROJECT_ROOT . '/config/auth.php';

        $this->app->singleton(Authentication::class, function ($app) {
            switch (AUTH_DRIVER) {
                case 'api':
                    $driver = new ApiAuthDriver($app[Cookie::class], $app[Encryptor::class]);
                    break;
                case 'session':
                default:
                    $driver = new SessionAuthDriver($app[Session::class]);
            }
            return new Authentication($driver);
        });

        $this->app->singleton(Authorization::class, function ($app) {
            return new Authorization;
        });
    }
}
