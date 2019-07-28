<?php

/**
 * Escape variable for HTML output.
 *
 * @param mixed $var Variable to be escaped.
 * @return mixed Escaped variable, safe for HTML output.
 **/
function h($var) {
    if (is_array($var)) {
        return filter_var_array($var, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    }
    return htmlspecialchars($var);
}


/**
 * Encode string for URL.
 *
 * @param string $str String to be encoded.
 * @return string Encoded string, suitable for URL.
 **/
function u(string $str) {
    return urlencode($str);
}


/**
 * Var dump.
 *
 * @param mixed $var
 */
function d($var)
{
    echo '<pre>';
    var_dump($var);
    echo '</pre>';
}


/**
 * Var dump and die.
 *
 * @param mixed $var
 */
function dd($var)
{
    d($var);
    exit;
}


/**
 * Pretty print.
 *
 * @param mixed $var
 */
function p($var)
{
    echo '<pre>';
    print_r($var);
    echo '</pre>';
}


/**
 * Pretty print and die.
 *
 * @param mixed $var
 */
function pd($var)
{
    p($var);
    exit;
}



/**
 * Convert URL_ROOT relative path to full URL.
 *
 * @param string $url
 */
function url(string $url)
{
    $url = trim(URL_ROOT, '/') . '/' . ltrim($url, '/');
    return $url;
}


/**
 * Redirect and exit.
 *
 * @param string $url URL to redirect to.
 */
function redirect(string $url)
{
    header('Location: ' . url($url));
    exit;
}


/**
 * Redirect to previous page.
 */
function back()
{
    $session = app('Bellona\Session\Session');
    $url = $session->get('back') ?? URL_ROOT;
    redirect($url);
}


/**
 * Resolve service from app container, or the container itself.
 *
 * @param string $serviceName Name of service to resolve.
 * @return object Resolved service, or app container.
 */
function app(string $serviceName = null)
{
    $app = \Bellona\Core\Application::getInstance();
    return isset($serviceName) ? $app[$serviceName] : $app;
}


/**
 * Output view to HTML.
 *
 * @param string $path Path to view file relative to app/views/.
 * @param array $data Data to pass to view.
 */
function render(string $path, array $data = [])
{
    app('Bellona\View\ViewFactory')->make($path)->render($data);
}


/**
 * Authorize an action.
 *
 * @see \Bellona\Auth\Authorization::can
 */
function can(string $action, $model, \App\Models\User $user = null)
{
    return app('Bellona\Auth\Authorization')->can($action, $model, $user);
}


/**
 * Retrieve item(s) from old array.
 *
 * @see \Bellona\Http\Request::old
 **/
function old($field = null)
{
    return app('Bellona\Http\Request')->old($field);
}


/**
 * Cache output for given duration.
 *
 * @param int $duration Duration to cache output for.
 */
function cache(int $duration = 0)
{
    app('Bellona\Http\Router')->cache($duration);
}


/**
 * Generate input field for spoofing HTTP verbs.
 *
 * @param string $verb HTTP verb to spoof.
 */
function spoofVerb(string $verb) {
    return '<input type="hidden" name="_method" value="' . strtoupper($verb) . '">';
}


/**
 * Recursively spread array.
 *
 * Returns array whose keys are all the keys leading to a value,
 * joined by dots.
 *
 * @param array $array Array to spread.
 * @return array Array after spreading.
 */
function spreadArray(array $array, string $delimiter = '.') {
    $result = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($array), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($iterator as $k => $v) {
        if (!$iterator->hasChildren()) {
            $p = [];
            $depth = $iterator->getDepth();
            for ($i = 0; $i <= $depth; $i++) {
                $p[] = $iterator->getSubIterator($i)->key();
            }
            $path = implode($delimiter, $p);
            $result[$path] = $v;
        }
    }
    return $result;
}
