<?php

namespace Bellona\Auth;

use App\Models\User;

interface AuthContract
{
    /**
     * Check if user is logged in.
     *
     * @return bool True if logged in; false otherwise.
     */
    public function isLoggedIn();


    /**
     * Get current logged in user instance.
     *
     * @return User|null
     */
    public function user();


    /**
     * Get current logged in user ID.
     *
     * @return string|int|null
     */
    public function id();


    /**
     * Login user.
     *
     * @param User User instance to login.
     */
    public function login(User $user);


    /**
     * Log user out.
     */
    public function logout();
}
