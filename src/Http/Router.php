<?php

namespace Bellona\Http;

use Bellona\Core\Application;
use Bellona\Http\Request;
use Bellona\Http\Route;
use Bellona\Session\Session;
use Bellona\Support\Facades\CSRF;

class Router
{
    /** @var Application $app Application instance. */
    private $app;

    /** @var Request $request Request instance. */
    private $request;

    /** @var Session $session Session instance. */
    private $session;

    /** @var string $routePrefix Prefix to prepend to route paths. */
    private $routePrefix = '';

    /** @var array $routes Registered routes for each HTTP verb. */
    private $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'PATCH' => [],
        'DELETE' => []
    ];

    /** @var Route $route Matching route. */
    private $route;

    /** @var array $routeParams Param name/value pairs from matching route. */
    private $routeParams;

    /** @var string $callback Callback registered to matching route. */
    private $callback;

    /** @var array $callbackParams Resolved params for callback. */
    private $callbackParams;

    /** @var array $middleware All available middleware, as defined in config file. */
    private $middleware = [];

    /** @var string $controller Controller registered to matching route. */
    private $controller;

    /** @var string $method Controller method registered to matching route. */
    private $method;

    /** @var Reflector $controllerReflector Reflector for controller. */
    private $controllerReflector;

    /** @var Reflector $callbackReflector Reflector for callback function/method. */
    private $callbackReflector;

    /** @var bool $cache Whether or not the output should be cached. */
    private $cache = false;

    /** @var int $cacheDuration How long the output should be cached. */
    private $cacheDuration = 0;


    public function __construct(Application $app, Request $request, Session $session)
    {
        $this->app = $app;
        $this->request = $request;
        $this->session = $session;
    }


    /**
     * Register GET route.
     *
     * @param string $path Path for which the route should be registered.
     * @param string|object $callback Closure or Controller@action to call.
     * @return Route Newly-registered route.
     */
    public function get(string $path, $callback)
    {
        return $this->registerRoute($path, $callback, ['GET']);
    }


    /**
     * Register POST route.
     *
     * @param string $path Path for which the route should be registered.
     * @param string|object $callback Closure or Controller@action to call.
     * @return Route Newly-registered route.
     */
    public function post(string $path, $callback)
    {
        return $this->registerRoute($path, $callback, ['POST']);
    }


    /**
     * Register PUT route.
     *
     * @param string $path Path for which the route should be registered.
     * @param string|object $callback Closure or Controller@action to call.
     * @return Route Newly-registered route.
     */
    public function put(string $path, $callback)
    {
        return $this->registerRoute($path, $callback, ['PUT']);
    }


    /**
     * Register PATCH route.
     *
     * @param string $path Path for which the route should be registered.
     * @param string|object $callback Closure or Controller@action to call.
     * @return Route Newly-registered route.
     */
    public function patch(string $path, $callback)
    {
        return $this->registerRoute($path, $callback, ['PATCH']);
    }


    /**
     * Register DELETE route.
     *
     * @param string $path Path for which the route should be registered.
     * @param string|object $callback Closure or Controller@action to call.
     * @return Route Newly-registered route.
     */
    public function delete(string $path, $callback)
    {
        return $this->registerRoute($path, $callback, ['DELETE']);
    }


    /**
     * Register route for multiple verbs.
     *
     * @param array $verbs Verbs to match.
     * @param string $path Path for which the route should be registered.
     * @param string|object $callback Closure or Controller@action to call.
     * @return Route Newly-registered route.
     */
    public function match(array $verbs, string $path, $callback)
    {
        return $this->registerRoute($path, $callback, $verbs);
    }


    /**
     * Register route for all verbs.
     *
     * @param string $path Path for which the route should be registered.
     * @param string|object $callback Closure or Controller@action to call.
     * @return Route Newly-registered route.
     */
    public function all(string $path, $callback)
    {
        return $this->registerRoute($path, $callback, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);
    }


    /**
     * Register route.
     *
     * @param string $path Path for which the route should be registered.
     * @param string|object $callback Closure or Controller@action to call.
     * @param array $verbs Verbs for which the route should be registered.
     * @return Route Newly-registered route.
     */
    public function registerRoute(string $path, $callback, array $verbs)
    {
        $path = $this->routePrefix . $path;
        $route = new Route($path, $callback);
        foreach ($verbs as $verb) {
            $this->routes[$verb][] = $route;
        }
        return $route;
    }


    /**
     * Route incoming request to matching route callback.
     */
    public function route()
    {
        // Set new CSRF token in response header.
        CSRF::setHeader();

        // Load web routes.
        require_once PROJECT_ROOT . '/routes/web.php';

        // Load api routes.
        $this->routePrefix = '/api';
        require_once PROJECT_ROOT . '/routes/api.php';


        // Get matching route.
        $url = $this->request->getUrl();
        $verb = $this->request->getVerb();
        $this->route = $this->findMatchingRoute($url, $verb);


        // Show 404 if no route matches request url.
        if (!$this->route) {
            http_response_code(404);
            render('404');
            exit;
        }

        // Check cache.
        if ($cachedFile = $this->checkCache()) {
            $this->serveCached($cachedFile);
            exit;
        }

        // Load middleware.
        $this->middleware = require_once PROJECT_ROOT . '/config/middleware.php';

        // Get captured params as name/value pairs.
        $this->routeParams = $this->route->getParams();

        // Get route callback.
        $this->callback = $this->route->getCallback();

        if (is_string($this->callback)) {
            $atIndex = strpos($this->callback, '@');
            $this->controller = 'App\Controllers\\' . substr($this->callback, 0, $atIndex);
            $this->method = substr($this->callback, $atIndex + 1);
            $this->controllerReflector = new \ReflectionClass($this->controller);
            $this->callbackReflector = $this->controllerReflector->getMethod($this->method);
        } else {
            $this->callbackReflector = new \ReflectionFunction($this->callback);
        }

        // Resolve callback params.
        $this->callbackParams = $this->resolveParams($this->callbackReflector, $this->routeParams);

        // Get middleware for matching route.
        $middleware = $this->route->getMiddleware();

        // Add middleware group for web/api route.
        if ($this->request->isPost()) {
            array_unshift($middleware, 'csrf');
        }

        // Run middleware.
        $middleware = $this->resolveMiddleware($middleware);
        $this->runMiddleware($middleware);

        // Resolve callback params and call callback.
        $this->runCallback();

        // Save URL for backwards redirects.
        if ($this->request->isGet() && !$this->request->isApi()) {
            $this->session->set('back', $url);
        }
    }


    /**
     * Check registered routes for one which matches request URL.
     *
     * @param string $url
     * @param string $verb Request verb.
     */
    private function findMatchingRoute(string $url, string $verb)
    {
        foreach ($this->routes[$verb] as $route) {
            if ($route->matches($url)) {
                return $route;
            }
        }

        return false;
    }


    /**
     * Parse and resolve middleware.
     */
    private function resolveMiddleware(array $middleware)
    {
        $resolved = [];

        foreach ($middleware as $name) {
            $exploded = explode(':', $name);
            $name = array_shift($exploded);
            $params = explode(',', $exploded[0] ?? '');
            foreach ($params as $i => $param) {
                if (array_key_exists($param, $this->callbackParams)) {
                    $params[$i] = $this->callbackParams[$param];
                }
            }
            $resolved[$name] = $params;
        }

        return $resolved;
    }


    /**
     * Run all middleware registered to route before callback.
     *
     * @param array $middleware Middleware to run.
     */
    private function runMiddleware(array $middleware)
    {
        foreach ($middleware as $name => $params) {
            $instance = new $this->middleware[$name];
            $instance->run($this->request, ...$params);
        }
    }


    /**
     * Run Controller@action callback.
     */
    private function runCallback()
    {
        if (isset($this->controller)) {
            $this->runControllerCallback();
        } else {
            $this->runClosureCallback();
        }
    }


    /**
     * Run Controller@action callback.
     */
    private function runControllerCallback()
    {
        // Resolve and run controller constructor.
        if ($constructor = $this->controllerReflector->getConstructor()) {
            $constructorParams = array_values($this->resolveParams($constructor));
        } else {
            $constructorParams = [];
        }
        $controller = new $this->controller(...$constructorParams);
        // Resolve and run controller middleware.
        $middleware = $controller->getMiddleware();
        $middleware = $this->resolveMiddleware($middleware);
        $this->runMiddleware($middleware);
        // Run callback action.
        call_user_func_array([$controller, $this->method], $this->callbackParams);
    }


    /**
     * Run closure callback.
     */
    private function runClosureCallback()
    {
        call_user_func_array($this->callback, $this->callbackParams);
    }


    /**
     * Resolve type-hinted parameters from a function or method.
     *
     * @param Reflector $reflector
     * @param array $params Captured params to splice resolved instances into.
     * @return array Params with resolved instances spliced in.
     */
    private function resolveParams(\Reflector $reflector, array $params = [])
    {
        foreach ($reflector->getParameters() as $index => $param) {
            // If param was not type-hinted, $class will be null.
            if ($class = $param->getClass()) {
                $className = $class->getName();
                $paramName = $param->getName();
                // If param name matches a named route param, it is a
                // model instance, so retrieve it from the database.
                // Otherwise, resolve it from the service container.
                if (array_key_exists($paramName, $params)) {
                    $resolved = $className::find($params[$paramName]);
                    if (!$resolved) {
                        if (!$param->allowsNull()) {
                            http_response_code(403);
                            include_once APP_ROOT . '/views/404.php';
                            exit;
                        }
                    }
                } else {
                    $resolved = $this->app[$className];
                }
                $params = array_slice($params, 0, $index, true) + [$paramName => $resolved] + array_slice($params, $index, null, true);
            }
        }
        return $params;
    }


    /**
     * Check and serve output from cache.
     */
    private function checkCache()
    {
        $filename = preg_replace('/[\/{}]/', '_', $this->route->getPath());
        $pattern = '~' . $filename . '_[0-9]+' . '\.html' . '~';
        $existing = scandir(PROJECT_ROOT . '/storage/cache/');
        $matches = preg_grep($pattern, $existing);
        if (!$matches) return false;
        $match = array_shift($matches);
        if (!$this->checkCachedDuration($match)) return false;
        return $match;
    }


    /**
     * Check cached file is not outdated.
     *
     * @param string $filename
     *
     * @return bool True if not outdated; false otherwise.
     */
    private function checkCachedDuration(string $filename)
    {
        $expiration = (int)substr($filename, strrpos($filename, '_') + 1, strrpos($filename, '.') - 1);
        if ($expiration === 0) return true;
        $expired = $expiration < time();
        if (!$expired) return true;
        unlink(PROJECT_ROOT . '/storage/cache/' . $filename);
        return false;
    }



    /**
     * Serve file from cache directory.
     *
     * @param string $filename File to serve.
     */
    private function serveCached(string $filename)
    {
        require_once PROJECT_ROOT . '/storage/cache/' . $filename;
    }


    /**
     * Cache output.
     *
     * @param int $duration
     */
    public function cache(int $duration = 0)
    {
        $this->cache = true;
        $this->cacheDuration = $duration;
    }


    /**
     * Cache output.
     */
    private function cacheOutput()
    {
        $expiration = $this->cacheDuration === 0 ? 0 : time() + $this->cacheDuration;
        $filename = preg_replace('/[\/{}]/', '_', $this->route->getPath()) . '_' . $expiration;
        file_put_contents(PROJECT_ROOT . '/storage/cache/' . $filename . '.html', ob_get_contents());
    }


    public function __destruct()
    {
        if ($this->cache) {
            $this->cacheOutput();
        }
    }
}
