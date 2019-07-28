<?php

namespace Bellona\Core;

use ArrayAccess;

class Application implements ArrayAccess
{
    /** @var Application $instance Instance of the Application.  */
    private static $instance;

    /** @var bool $hasBooted Ensures app can only be booted once. */
    private $hasBooted = false;

    /** @var array $staged Registered services. */
    private $registered = [];

    /** @var array $deferred Deferred services. */
    private $deferred = [];

    /** @var array $booted Booted services. */
    private $booted = [];


    /**
     * Register services required for the app to run.
     *
     * @param array $serviceProviders Service providers to register.
     */
    public function __construct(array $serviceProviders)
    {
        // Set app instance as static property and add to booted array.
        self::$instance = $this;
        $this->booted[self::class] = $this;

        // Register all services.
        foreach ($serviceProviders as $serviceProvider) {
            $this->registerService($serviceProvider);
        }
    }


    /**
     * Register a service to the app by calling the register method
     * of the service provider.
     *
     * @param string $serviceProvider Service provider class name.
     */
    private function registerService(string $serviceProvider)
    {
        $serviceProvider = new $serviceProvider;
        if ($serviceProvider->defer) {
            foreach ($serviceProvider->services as $serviceName) {
                $this->deferred[$serviceName] = $serviceProvider;
            }
        } else {
            $serviceProvider->register();
        }
    }


    /**
     * Register deferred service.
     *
     * @param string $serviceName
     */
    private function registerDeferred(string $serviceName)
    {
        $this->deferred[$serviceName]->register();
        unset($this->deferred[$serviceName]);
    }


    /**
     * Bind service provider callback to registered array, wrapped in a closure.
     *
     * @param string $serviceName Service name.
     * @param object The closure object (function) to run when resolving the service.
     */
    public function bind(string $serviceName, \Closure $callback)
    {
        $this->registered[$serviceName] = [$callback, false];
    }


    /**
     * Bind service provider callback to registered array.
     *
     * @param string $serviceName Service name.
     * @param object The closure object (function) to run when resolving the service.
     */
    public function singleton(string $serviceName, \Closure $callback)
    {
        $this->registered[$serviceName] = [$callback, true];
    }


    /**
     * Boot services.
     */
    public function boot()
    {
        if ($this->hasBooted) return;

        $this->hasBooted = true;

        $this->bootServices();
    }


    /**
     * Boot all registered services.
     */
    private function bootServices()
    {
        foreach ($this->registered as $serviceName => [$callback, $shared]) {
            $this->bootService($serviceName);
        }
    }


    /**
     * Boot services by running its callback and storing in booted array.
     *
     * @param string $serviceName Name of service to boot.
     */
    public function bootService(string $serviceName)
    {
        if (array_key_exists($serviceName, $this->booted)) return;
        [$callback, $shared] = $this->registered[$serviceName];
        $this->booted[$serviceName] = $shared ? $callback($this) : $callback;
        unset($this->registered[$serviceName]);
    }


    /**
     * Resolve and return a service instance.
     *
     * @param string $serviceName Service instance to resolve.
     * @return object Service instance.
     */
    public function resolve(string $serviceName)
    {
        if (!array_key_exists($serviceName, $this->booted)) {
            if (array_key_exists($serviceName, $this->deferred)) {
                $this->registerDeferred($serviceName);
            }
            if (array_key_exists($serviceName, $this->registered)) {
                $this->bootService($serviceName);
            } else {
                throw new \Exception('Service not registered.');
            }
        }
        $service = $this->booted[$serviceName];
        return $service instanceof \Closure ? $service($this) : $service;
    }


    /**
     * Return instance of the app.
     *
     * @return App App instance.
     */
    public static function getInstance()
    {
        return self::$instance;
    }


    /**
     * Deny properties being assigned to app object.
     */
    public function offsetSet($offset, $value)
    {
        throw new \Exception('Can not register services into the container outside of service providers.');
    }


    /**
     * Check if service exists.
     */
    public function offsetExists($offset)
    {
        if (isset($this->registered[$offset])) {
            return true;
        }
        if (isset($this->deferred[$offset])) {
            return true;
        }
        if (isset($this->booted[$offset])) {
            return true;
        }
        return false;
    }



    /**
     * Deny ability to unset services from container.
     */
    public function offsetUnset($offset)
    {
        throw new \Exception('Can not unset services from the container.');
    }


    /**
     * Resolve service from container.
     */
    public function offsetGet($offset)
    {
        return $this->resolve($offset);
    }
}
