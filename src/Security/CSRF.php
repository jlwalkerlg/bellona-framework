<?php

namespace Bellona\Security;

use Bellona\Http\Request;
use Bellona\Session\Session;

class CSRF
{
    /** @var Request $request Request instance. */
    private $request;

    /** @var Session $session Session instance. */
    private $session;

    /** @var string $token CSRF token */
    private $token;

    /** @var string $oldToken Token set in session during previous request. */
    private $oldToken;

    /** @var int $oldTime Time at which old token was set. */
    private $oldTime;


    public function __construct(Request $request, Session $session)
    {
        $this->request = $request;
        $this->session = $session;

        $this->oldToken = $session->getClean('csrf_token');
        $this->oldTime = (int)$session->getClean('csrf_token_time');
    }


    /**
     * Generate and save CSRF token.
     *
     * @return string CSRF token.
     */
    private function generateToken()
    {
        $token = md5(uniqid(rand(), true));
        $this->session->set('csrf_token', $token);
        $this->session->set('csrf_token_time', time());
        $this->token = $token;
        return $token;
    }


    /**
     * Get token.
     *
     * @return string CSRF token.
     */
    public function token()
    {
        return $this->token ?? $this->generateToken();
    }


    /**
     * Set CSRF token in header for SPAs.
     */
    public function setHeader()
    {
        $token = $this->token ?? $this->generateToken();
        header('XSRF-TOKEN: ' . $token);
    }


    /**
     * Generate CSRF token and return a hidden input field
     * containing the token for inclusion in a form.
     *
     * @return string HTML form input.
     */
    public function input()
    {
        $token = $this->token();
        return '<input type="hidden" name="csrf_token" value="' . $token . '">' . "\n";
    }


    /**
     * Check CSRF token is in POST superglobal, matches that stored
     * in the session, and is not older than a day.
     *
     * @return bool True if token is valid; false otherwise.
     */
    public function validate()
    {
        if ($this->request->isAjax()) {
            $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        } else {
            $csrf_token = $this->request->data('csrf_token');
        }

        if (!isset($csrf_token)) {
            return false;
        }

        if ($this->oldToken !== $csrf_token) {
            return false;
        }

        if (time() - $this->oldTime > 60 * 60 * 24) {
            return false;
        }

        return true;
    }
}
