<?php

namespace Bellona\Session;

use Bellona\Cookie\Cookie;
use Bellona\Encryption\Encryptor;

class CookieSessionDriver implements SessionContract
{
    /** @var Cookie $cookie Cookie instance. */
    private $cookie;

    /** @var Encryptor $encryptor Encryptor instance. */
    private $encryptor;

    /** @var array $data Decoded session data. */
    private $data;

    /** @var array $flashed Array of flashed values from previous request. */
    private $flashed = [];


    public function __construct(Cookie $cookie, Encryptor $encryptor)
    {
        $this->cookie = $cookie;
        $this->encryptor = $encryptor;
        $this->flashed = $this->getClean('flash') ?? [];
    }


    public function get(string $key)
    {
        if (array_key_exists($key, $this->flashed)) {
            return $this->flashed[$key];
        }
        $data = $this->getCookieData();
        return $data[$key] ?? null;
    }


    private function getCookieData()
    {
        if (isset($this->data)) return $this->data;

        $cookie = $this->cookie->get(SESSION_COOKIE_NAME);

        if (!isset($cookie)) return null;

        $data = $this->encryptor->decryptData($cookie);

        $this->data = $data;

        return $data;
    }


    public function set(string $key, $value)
    {
        $data = $this->getCookieData();
        $data[$key] = $value;

        return $this->setCookieData($data);
    }


    private function setCookieData(array $data = null)
    {
        $this->data = $data;
    }


    public function unset(string $key)
    {
        $data = $this->getCookieData();
        unset($data[$key]);
        return $this->setCookieData($data);
    }


    public function getClean(string $key)
    {
        $value = $this->get($key);
        $this->unset($key);
        return $value;
    }


    public function flash(string $key, $value)
    {
        $data = $this->getCookieData();
        $data['flash'][$key] = $value;
        $this->setCookieData($data);
    }


    public function __destruct()
    {
        if (!isset($this->data)) return;

        $encodedData = $this->encryptor->encryptData($this->data);

        return $this->cookie->set(SESSION_COOKIE_NAME, $encodedData, SESSION_COOKIE_DURATION, null, SESSION_COOKIE_DOMAIN, SESSION_COOKIE_SECURE, SESSION_COOKIE_HTTP_ONLY);
    }
}
