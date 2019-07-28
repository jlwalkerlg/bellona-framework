<?php

namespace Bellona\Http;

class Route
{
    /** @var string $path URL for which the route is registered. */
    private $path;

    /** @var string $pattern URL converted to a regex pattern. */
    private $pattern;

    /** @var string|\Closure $callback Controller@action or function to call back. */
    private $callback;

    /** @var array $capturedParams Parameters captured from the matching URL. */
    private $capturedParams;

    /** @var array $namedParams Parameters named in path. */
    private $namedParams;

    /** @var array $wheres Custom regex for named params. */
    private $wheres = [];

    /** @var array $middleware Middleware to run before callback. */
    private $middleware = [];


    public function __construct(string $path, $callback)
    {
        $this->path = trim($path, '/');
        $this->callback = $callback;
    }


    /**
     * Set middleware for the route.
     *
     * @param string $middleware List of middleware class names.
     * @return Route
     */
    public function middleware(...$middleware)
    {
        $this->middleware = $middleware;
        return $this;
    }


    /**
     * Impose regex on route parameters.
     *
     * @param mixed $wheres Parameter(s) and regex condition(s) to impose.
     * @return Route
     */
    public function where(...$wheres)
    {
        if (!is_array(current($wheres))) {
            $namedParam = $wheres[0];
            $regex = $wheres[1];
            $this->wheres[] = [$namedParam => $regex];
        } else {
            foreach ($wheres as $where) {
                $this->wheres[] = $where;
            }
        }
        return $this;
    }


    /**
     * Check if request URL matches registered path.
     *
     * @param string $url Request URL.
     * @return bool True if matches; false otherwise.
     */
    public function matches(string $url)
    {
        $pattern = $this->getPattern();

        $matches = preg_match($pattern, $url, $capturedParams);

        if (!$matches) return false;

        array_shift($capturedParams);

        $this->capturedParams = $capturedParams;

        return true;
    }


    /**
     * Convert registered path to regex pattern.
     *
     * @return string Regex pattern.
     */
    private function getPattern()
    {
        if (!isset($this->pattern)) {
            $this->setPattern();
        }
        return $this->pattern;
    }


    /**
     * Convert registered path to regex and store pattern.
     */
    private function setPattern()
    {
        $pattern = $this->imposeCustomRegex();
        $pattern = preg_replace('/{\w+}/', '(\w+)', $pattern);
        $this->pattern = "~^$pattern$~";
    }


    /**
     * Impose custom regex on named route param.
     *
     * @return string Pattern with custom regex spliced in.
     */
    private function imposeCustomRegex()
    {
        $pattern = $this->path;
        foreach ($this->wheres as $where) {
            $param = array_key_first($where);
            $regex = $where[$param];
            $pattern = str_replace('{' . $param . '}', '(' . $regex . ')', $pattern);
        }
        return $pattern;
    }


    /**
     * Get path for which route was registered.
     *
     * @return string Path.
     */
    public function getPath()
    {
        return $this->path;
    }


    /**
     * Get registered callback.
     *
     * @return string Registered callback.
     */
    public function getCallback()
    {
        return $this->callback;
    }


    /**
     * Get captured parameters.
     *
     * @return array Captured params.
     */
    public function getCapturedParams()
    {
        return $this->capturedParams;
    }


    /**
     * Get named parameters from path.
     *
     * @return array Array of parameter names.
     */
    public function getnamedParams()
    {
        if (!isset($this->namedParams)) {
            $this->setNamedParams();
        }
        return $this->namedParams;
    }


    /**
     * Store named parameters.
     */
    private function setNamedParams()
    {
        preg_match_all('/{\w+}/', $this->path, $namedParams);
        $this->namedParams = array_map(function ($name) {
            return trim($name, '{}');
        }, $namedParams[0]);
    }


    /**
     * Get associative array of captured params.
     *
     * Keys are parameter names from path, values are
     * the corresponding captured values.
     *
     * @return array
     */
    public function getParams()
    {
        if (!isset($this->params)) {
            $this->setParams();
        }
        return $this->params;
    }


    /**
     * Set params using named params and captured values.
     */
    private function setParams()
    {
        $names = $this->getnamedParams();
        $values = $this->getCapturedParams();
        $this->params = array_combine($names, $values);
    }


    /**
     * Retrieve middleware for route.
     */
    public function getMiddleware()
    {
        return $this->middleware;
    }
}
