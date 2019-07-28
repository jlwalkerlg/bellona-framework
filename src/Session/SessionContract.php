<?php

namespace Bellona\Session;

interface SessionContract
{
    /**
     * Get value stored in session.
     *
     * @param string $key Key of value to retrieve from session.
     * @return mixed The value, or null if not found.
     */
    public function get(string $key);


    /**
     * Set a value in the session.
     *
     * @param string $key Key to store value under.
     * @param mixed $value Value to store under key in session.
     */
    public function set(string $key, $value);


    /**
     * Unset a value from the session.
     *
     * @param string $key Key of value in session to unset.
     */
    public function unset(string $key);


    /**
     * Get and unset value from session.
     *
     * @param string $key Key of value in session to get.
     * @return mixed The value, or null if not found.
     */
    public function getClean(string $key);


    /**
     * Flash variable to session.
     *
     * @param string $key Key with which to store variable.
     * @param mixed $value Variable to flash.
     */
    public function flash(string $key, $value);
}
