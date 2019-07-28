<?php

namespace Bellona\Auth;

use Bellona\Auth\AuthContract;
use App\Models\User;
use Bellona\Cookie\Cookie;
use Bellona\Encryption\Encryptor;

class ApiAuthDriver implements AuthContract
{
    /** @var Cookie $cookie Cookie instance. */
    private $cookie;

    /** @var Encryptor $encryptor Encryptor instance. */
    private $encryptor;

    /** @var User $user Current logged in user instance. */
    private $user;

    /** @var array $authData Auth data retrieved from auth cookie. */
    private $authData;


    public function __construct(Cookie $cookie, Encryptor $encryptor)
    {
        $this->cookie = $cookie;
        $this->encryptor = $encryptor;

        if ($this->cookie->exists(AUTH_COOKIE_NAME)) {
            $this->setAuthData();
            $this->setUser();
        }
    }


    private function setAuthData()
    {
        $authCookie = $this->cookie->get(AUTH_COOKIE_NAME);
        $this->authData = $this->encryptor->decryptData($authCookie);
    }


    private function setUser()
    {
        $this->user = User::find($this->authData['user_id']);
    }


    public function isLoggedIn()
    {
        if (!isset($this->authData)) return null;
        $expiration = $this->authData['login_time'] + $this->authData['duration'];
        return $expiration > time();
    }


    public function user()
    {
        if (!$this->isLoggedIn()) return null;

        return $this->user;
    }


    public function id()
    {
        if (!isset($this->authData)) return null;
        return $this->authData['user_id'];
    }


    public function login(User $user)
    {
        $data = [
            'user_id' => $user->id,
            'login_time' => time(),
            'duration' => AUTH_COOKIE_DURATION
        ];

        $authCookie = $this->encryptor->encryptData($data);

        $this->cookie->set(AUTH_COOKIE_NAME, $authCookie, AUTH_COOKIE_DURATION, null, AUTH_COOKIE_DOMAIN, AUTH_COOKIE_SECURE, AUTH_COOKIE_HTTP_ONLY);
    }


    public function logout()
    {
        $this->cookie->delete(AUTH_COOKIE_NAME);
    }
}
