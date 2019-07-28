<?php

namespace Bellona\Http;

class Controller
{
    /** @var array $middleware Middleware to run for all controller methods. */
    protected $middleware = [];


    /**
     * Register middleware to run before all controller methods.
     *
     * @param string $middlewareName Name of middleware.
     */
    protected function middleware(string $middlewareName)
    {
        $this->middleware[] = $middlewareName;
    }


    /**
     * Retrieve middleware registered to controller.
     *
     * @return array Registerered middleware.
     */
    public function getMiddleware()
    {
        return $this->middleware;
    }
}
