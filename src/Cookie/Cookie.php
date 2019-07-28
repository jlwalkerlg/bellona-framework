<?php

namespace Bellona\Cookie;

class Cookie
{
    /**
     * Set cookie.
     *
     * @param string $name Name of cookie to set.
     * @param string $value Value of cookie to set.
     * @param int $duration Duration for which cookie should exist.
     * @param string $path Server path the cookie will be available on.
     * @param string $domain Domain from which the cookie will be available.
     * @param bool $secure Whether the cookie should only sent over secure connections.
     * @param bool $httponly Whether the cookie should only be available through HTTP requests (therefore not JS).
     * @return bool True if cookie succesffully set; false otherwise.
     */
    public function set(string $name, string $value, int $duration = null, string $path = null, string $domain = null, bool $secure = null, bool $httponly = null)
    {
        $duration = $duration ?? 0;
        $path = $path ?? '/';

        $expire = time() + $duration;
        return setCookie($name, $value, $expire, $path, $domain, $secure, $httponly);
    }


    /**
     * Delete a cookie.
     *
     * @param string $name Name of cookie to delete.
     * @return bool True if cookie successfully deleted; false otherwise.
     */
    public function delete(string $name)
    {
        return $this->set($name, '', 1);
    }


    /**
     * Retrieve cookie.
     *
     * @param string $name Name of cookie to retrieve.
     * @return string|null Cookie, or null if not set.
     */
    public function get(string $name)
    {
        return $_COOKIE[$name] ?? null;
    }


    /**
     * Check if a cookie exists.
     *
     * @param string $name Name of cookie to check.
     * @return bool True if cookie is set; false otherwise.
     */
    public function exists(string $name)
    {
        return isset($_COOKIE[$name]);
    }
}
