<?php

namespace Bellona\Support\Facades;

use Bellona\Encryption\Encryptor;

class Encrypt extends Facade
{
    protected static $service = Encryptor::class;
}
