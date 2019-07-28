<?php

namespace Bellona\Auth;

use Bellona\Session\Session;
use Bellona\Auth\AuthContract;
use App\Models\User;

class SessionAuthDriver implements AuthContract
{
    /** @var Session $session Session instance. */
    private $session;

    /** @var User $user Current logged in user instance. */
    private $user;


    public function __construct(Session $session)
    {
        $this->session = $session;
    }


    public function isLoggedIn()
    {
        return $this->session->get('user_id') !== null;
    }


    public function user()
    {
        if (!isset($this->user)) {
            $this->user = User::find($this->session->get('user_id'));
        }

        return $this->user;
    }


    public function id()
    {
        return $this->session->get('user_id');
    }


    public function login(User $user)
    {
        if (SESSION_DRIVER === 'session') {
            session_regenerate_id();
        }
        $this->session->set('user_id', $user->id);
        $this->session->set('login_time', time());
    }


    public function logout()
    {
        $this->session->unset('user_id');
        $this->session->unset('login_time');
    }
}
