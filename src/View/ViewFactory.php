<?php

namespace Bellona\View;

use Bellona\View\View;
use Bellona\Session\Session;

class ViewFactory
{
    /** @var array $sharedData Data to share between views. */
    private $sharedData = [];


    public function __construct(Session $session)
    {
        $this->sharedData['errors'] = $session->getClean('errors') ?? [];
    }


    public function make(string $path)
    {
        return new View($path, $this->sharedData);
    }


    /**
     * Add data to share between views.
     *
     * @param string $name
     * @param mixed $value
     */
    public function share(string $name, $value)
    {
        $this->sharedData[$name] = $value;
    }
}
