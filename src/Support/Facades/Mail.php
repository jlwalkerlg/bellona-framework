<?php

namespace Bellona\Support\Facades;

use Bellona\Support\Facades\Facade;
use App\Lib\Mail\MailFactory;

class Mail extends Facade
{
    protected static $service = MailFactory::class;
}
