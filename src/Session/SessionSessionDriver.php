<?php

namespace Bellona\Session;

class SessionSessionDriver implements SessionContract
{
    /** @var array $flashed Array of flashed values from previous request. */
    private $flashed = [];


    public function __construct()
    {
        session_start();
        $this->flashed = $this->getClean('flash') ?? [];
    }


    public function get(string $key)
    {
        if (array_key_exists($key, $this->flashed)) {
            return $this->flashed[$key];
        }
        return $_SESSION[$key] ?? null;
    }


    public function set(string $key, $value)
    {
        $_SESSION[$key] = $value;
    }


    public function unset(string $key)
    {
        unset($_SESSION[$key]);
    }


    public function getClean(string $key)
    {
        $value = $this->get($key);
        unset($_SESSION[$key]);
        return $value;
    }


    public function flash(string $key, $value)
    {
        $_SESSION['flash'][$key] = $value;
    }
}
