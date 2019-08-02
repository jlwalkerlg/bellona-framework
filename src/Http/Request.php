<?php

namespace Bellona\Http;

use Bellona\Session\Session;
use Bellona\Validation\Validator;
use Bellona\Uploads\FileUpload;

class Request
{
    /** @var Session $session Session instance. */
    private $session;

    /** @var string $url Request URL, without leading/trailing slash. */
    private $url;

    /** @var array $data User post data. */
    private $data;

    /** @var array $files Re-organised files superglobal. */
    private $files;

    /** @var array $uploads Instantiated files. */
    private $uploads;

    /** @var array $old Saved post data from previous request. */
    private $old;

    /** @var array $errors Errors retrieved from session. */
    private $errors;


    public function __construct(Session $session)
    {
        $this->session = $session;

        $this->url = rtrim($_GET['url'] ?? '', '/') . '/';

        $postData = $_POST;
        $ajaxData = json_decode(file_get_contents('php://input'), true) ?? [];
        $this->data = array_merge($postData, $ajaxData);

        [$this->files, $this->uploads] = $this->unpackFiles();

        $this->old = $session->getClean('old');

        $this->errors = $session->getClean('errors');
    }


    /**
     * Unpack $_FILES.
     *
     * @return array Reordered $_FILES superglobal, and instantiated files.
     */
    private function unpackFiles()
    {
        $files = [];
        $uploads = [];

        foreach ($_FILES as $key => $value) {
            if (!is_array($value['name'])) {
                $files[$key] = $value;
                $uploads[$key] = new FileUpload($value);
            } else {
                [$arrays, $objects] = $this->unspreadNestedFiles([$key => $value]);
                $files = array_merge($files, $arrays);
                $uploads = array_merge($uploads, $objects);
            }
        }

        return [$files, $uploads];
    }



    /**
     * Unspread a (nested) $_FILES array.
     *
     * @param array $array Sub-array nested in $_FILES array.
     * @return array Reordered sub-array, and instantiated files.
     */
    private function unspreadNestedFiles(array $array)
    {
        $filePaths = spreadArray($array, '/');

        $files = [];
        $uploads = [];
        $currentFiles = &$files;
        $currentUploads = &$uploads;

        foreach ($filePaths as $path => $value) {
            $keys = explode('/', $path);
            $attr = array_splice($keys, 1, 1);
            $keys = array_merge($keys, $attr);
            $lastKey = end($keys);
            foreach ($keys as $key) {
                if ($key === $lastKey) {
                    $currentFiles[$key] = $value;
                    $currentUploads[$key] = $value;
                } else {
                    $currentFiles[$key] = $currentFiles[$key] ?? null;
                    $currentUploads[$key] = $currentUploads[$key] ?? null;
                }
                if ($key === 'size') {
                    $currentUploads = new FileUpload($currentUploads);
                } else {
                    $currentFiles = &$currentFiles[$key];
                    $currentUploads = &$currentUploads[$key];
                }
            }
            $currentFiles = &$files;
            $currentUploads = &$uploads;
        }

        return [$files, $uploads];
    }



    /**
     * Return request URL.
     *
     * @return string request URL.
     */
    public function getUrl()
    {
        return $this->url;
    }


    /**
     * Check if request method is GET.
     *
     * @return bool True if request method is GET; false otherwise.
     */
    public function isGet()
    {
        return $_SERVER['REQUEST_METHOD'] === 'GET';
    }


    /**
     * Check if request method is POST.
     *
     * @return bool True if request method is POST; false otherwise.
     */
    public function isPost()
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }


    /**
     * Return request verb.
     *
     * PUT, PATCH, and DELETE verbs must be spoofed
     * in forms using the spoofVerb() helper function.
     *
     * @return string Request verb.
     **/
    public function getVerb()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            return 'GET';
        } else {
            return strtoupper($this->data('_method') ?? 'POST');
        }
    }


    /**
     * Retrieve item(s) from data array.
     *
     * @param mixed $field Key(s) corresponding to the item(s) to retrieve.
     * @return mixed Item(s) from data array, or null if not found.
     **/
    public function data($field = null)
    {
        return $this->retrieve('data', $field);
    }


    /**
     * Retrieve file(s) from files array.
     *
     * @param mixed $field Key(s) corresponding to the file(s) to retrieve.
     * @return mixed File(s) from files array, or null if not found.
     */
    public function file($field = null)
    {
        return $this->retrieve('files', $field);
    }


    /**
     * Retrieve instantiated FileUpload(s) from upload array.
     *
     * @param mixed $field Key(s) corresponding to the upload(s) to retrieve.
     * @return mixed Upload(s) from uploads array, or null if not found.
     */
    public function upload($field = null)
    {
        return $this->retrieve('uploads', $field);
    }


    /**
     * Retrieve item(s) from old array.
     *
     * @param mixed $field Key(s) corresponding to the item(s) to retrieve.
     * @return mixed Item(s) from old array, or null if not found.
     **/
    public function old($field = null)
    {
        return $this->retrieve('old', $field);
    }


    /**
     * Retrieve errors array.
     *
     * @return array
     */
    public function errors()
    {
        return $this->errors;
    }


    /**
     * Retrieve item(s) from data, files, or old array.
     *
     * If no fields are given, the entire relevant array is returned.
     * If a string is given, the corrseponding item is returned.
     * If an array is given, an array of items corrseponding to each
     * array element is returned.
     *
     * @param string $arr Array from which to retrieve item(s) from.
     * @param mixed $field Key(s) corresponding to the item(s) to retrieve.
     * @return mixed Item(s) from relevant array, or null if not found.
     **/
    private function retrieve(string $arr, $field = null)
    {
        if (!isset($field)) {
            return $this->$arr;
        }
        if (is_string($field)) {
            if (strpos($field, '.') !== false) {
                return $this->retrieveNested($arr, $field);
            } else {
                return $this->$arr[$field] ?? null;
            }
        }
        if (is_array($field)) {
            $result = [];
            foreach ($field as $key) {
                if (strpos($key, '.') !== false) {
                    $result[$key] = $this->retrieveNested($arr, $key);
                } else {
                    $result[$key] = $this->$arr[$key] ?? null;
                }
            }
            return $result;
        }
    }


    /**
     * Retrieve nested field from array.
     *
     * @param string $arr Array from which to retrieve item(s) from.
     * @param mixed $field Keys corresponding to the item(s) to retrieve, nested with dot notation.
     * @return mixed Item(s) from relevant array, or null if not found.
     */
    private function retrieveNested(string $arr, string $fields)
    {
        $keys = explode('.', $fields);
        $result = $this->$arr[array_shift($keys)] ?? null;
        foreach ($keys as $key) {
            if (!is_array($result)) return null;
            $result = $result[$key] ?? null;
        }
        return $result;
    }


    /**
     * Validate incoming request
     *
     * @param array $rules
     */
    public function validate(array $rules)
    {
        $fields = array_keys($rules);
        $postData = $this->data($fields);
        $uploads = array_filter($this->upload($fields), function ($item) {
            return $item !== null;
        });
        $data = array_merge($postData, $uploads);
        $validator = new Validator($data, $rules);
        $validator->run();
        if (!$validator->validates()) {
            $errors = $validator->getErrors();
            $this->session->flash('errors', $errors);
            $this->save($fields);
            back();
        }
    }


    /**
     * Store data in session.
     *
     * @param string|array $field Fields to store.
     */
    public function save($field = null)
    {
        if (!isset($field)) {
            $this->session->set('old', $this->data());
        }
        if (is_string($field)) {
            $this->session->set('old', $this->data($field));
        }
        if (is_array($field)) {
            $items = [];
            foreach ($field as $key) {
                $items[$key] = $this->data($key);
            }
            $this->session->set('old', $items);
        }
    }


    /**
     * Checks if request is meant for an API.
     *
     * @return bool True if api request; false otherwise.
     */
    public function isApi()
    {
        return strpos($this->url, 'api/') === 0;
    }


    /**
     * Checks if the request is made from an ajax call.
     *
     * @return bool True if ajax request; false otherwise.
     */
    public function isAjax()
    {
        return ($_SERVER['HTTP_X_REQUEST_WITH'] ?? null) === 'XMLHttpRequest';
    }
}
