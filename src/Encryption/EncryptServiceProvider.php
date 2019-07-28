<?php

namespace Bellona\Encryption;

use Bellona\Support\ServiceProvider;
use Bellona\Encryption\Encryptor;

class EncryptServiceProvider extends ServiceProvider
{
    public $defer = true;

    public $services = [Encryptor::class];

    public function register()
    {
        $this->app->singleton(Encryptor::class, function ($app) {
            return new Encryptor;
        });
    }
}
