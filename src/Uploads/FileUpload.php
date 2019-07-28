<?php

namespace Bellona\Uploads;

class FileUpload
{
    /** @var string $name Name of uploaded file, including extension. */
    private $name;

    /** @var string $extension Extension of uploaded file. */
    private $extension;

    /** @var string $type Type of uploaded file. */
    private $type;

    /** @var string $tmp_name tmp_name of uploaded file. */
    private $tmp_name;

    /** @var string $error Error code of uploaded file. */
    private $error;

    /** @var string $size Size of uploaded file in bytes. */
    private $size;

    /** @var string $destination Path to directory where file is to be saved. */
    private $destination;

    /** @var int $upload_max_filesize Maximum size allowed by server for uploaded file in bytes. */
    private $upload_max_filesize;

    /** @var int $maxSize Maximum size allowed for uploaded file in bytes. */
    private $maxSize;

    /** @var bool $overwrite Specifies whether or not uploaded file should overwrite existing files of the same name.
     *
     * Uploaded file will be renamed if this is false.
     */
    private $overwrite = false;

    /** @var bool $mkdir Specifies whether or not the destination directory should be created if it does not already exist. */
    private $mkdir = true;

    /** @var bool $dirCreated Identifies whether a new directory was created for the file(s). */
    private $dirCreated = false;

    /** @var array $permittedTypes Permitted MIME types and corrseponsing extensions. */
    private $permittedTypes = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/gif' => ['gif'],
        'image/png' => ['png'],
        'image/svg' => ['svg'],
        'image/webp' => ['webp']
    ];

    /** @var array $permittedExtensions Permitted extensions. */
    private $permittedExtensions = [
        'jpg', 'jpeg', 'gif', 'png', 'svg', 'webp'
    ];

    /** @var array $errors Array of errors regarding file upload. */
    private $errors = [];

    /** @var array $checked Validations which have already been checked. */
    private $checked = [
        'checkRequired' => false,
        'checkMaxSize' => false,
        'checkType' => false
    ];


    /**
     * Save file properties and destination on object.
     */
    public function __construct(array $file)
    {
        $upload_max_filesize = ini_get('upload_max_filesize');
        $this->upload_max_filesize = self::convertToBytes($upload_max_filesize);
        $this->maxSize = $this->upload_max_filesize;

        $this->name = $file['name'];
        $this->extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (!$file['tmp_name']) {
            $this->type = '';
        } else {
            $this->type = mime_content_type($file['tmp_name']); // false on failure
        }
        $this->tmp_name = $file['tmp_name'];
        $this->error = $file['error'];
        $this->size = $file['size'];
    }


    /**
     * Set upload options.
     *
     * @param array $options Array of options to set.
     */
    public function setOptions(array $options)
    {
        if (key_exists('name', $options)) {
            $this->setName($options['name']);
        }

        if (key_exists('types', $options)) {
            $this->setTypes($options['types']);
        }

        if (key_exists('maxSize', $options)) {
            $this->setMaxSize($options['maxSize']);
        }

        if (key_exists('overwrite', $options)) {
            $this->overwrite = (bool)$options['overwrite'];
        }

        if (key_exists('mkdir', $options)) {
            $this->mkdir = (bool)$options['mkdir'];
        }
    }


    /**
     * Check if file was uploaded (to tmp dir).
     *
     * @return bool True if file uploaded; false otherwise.
     */
    public function wasUploaded()
    {
        return $this->error !== 4;
    }


    /**
     * Retrieve errors message.
     *
     * If a string is given, the error message for the validation
     * corrseponding to the given key will be returned.
     * If no string is given, the first error message will be returned.
     *
     * @param string $key Key of error to return.
     * @return string Error message.
     */
    public function getError(string $key = null)
    {
        if (isset($key) && array_key_exists($key, $this->errors)) {
            return $this->errors[$key];
        }
        return reset($this->errors);
    }


    /**
     * Retrieve file name.
     *
     * @return string Name of file, including extension.
     */
    public function getName()
    {
        return $this->name;
    }


    /**
     * Set new name for uploaded file.
     *
     * @param string $name Name to save uploaded file as, excluding extension.
     */
    private function setName(string $name)
    {
        $this->name = $name . '.' . $this->extension;
    }


    /**
     * Override default permitted extensions.
     *
     * @param array $extensions Permitted extensions.
     */
    private function setTypes(array $extensions)
    {
        $this->permittedExtensions = $extensions;
    }


    /**
     * Override the default max file size.
     *
     * @param int $bytes Maximum bytes allowed for uploaded file.
     */
    private function setMaxSize(int $bytes)
    {
        if ($bytes > $this->upload_max_filesize) {
            throw new \Exception('Maximum size cannot exceed server limit for individual files: ' . self::convertFromBytes($this->upload_max_filesize));
        }
        if (is_numeric($bytes) && $bytes > 0) {
            $this->maxSize = $bytes;
        }
    }


    /**
     * Set destination directory to upload file to.
     *
     * @param string $destination Name of directory.
     */
    private function setDestination(string $destination)
    {
        // Create directory if requested and if it does not already exist.
        if ($this->mkdir && !is_dir($destination)) {
            if ($this->mkdir($destination)) {
                $this->dirCreated = true;
            } else {
                throw new \Exception('Problem creating new directory.');
            }
        }
        // Ensure destination folder exists and is writable.
        if (!is_dir($destination) || !is_writable($destination)) {
            throw new \Exception('Destination must be a valid, writable folder.');
        }
        // Append trailing slash to destination folder if it doesn't have one already.
        if ($destination[strlen($destination) - 1] !== '/') {
            $destination .= '/';
        }
        $this->destination = $destination;
    }


    /**
     * Create a new directory, which may be nested.
     *
     * @param string $destination Path the destination to create.
     */
    private function mkdir(string $destination)
    {
        $currentPath = '';
        $paths = explode('/', $destination);
        foreach ($paths as $path) {
            $currentPath .= $path . '/';
            if (file_exists($currentPath)) continue;
            if (!mkdir($currentPath)) return false;
        }
        return true;
    }


    /**
     * Check file was successfully uploaded to temporary directory.
     *
     * @return bool True if successful; false otherwise.
     */
    public function checkRequired()
    {
        $this->checked['checkRequired'] = true;

        if ($this->error === 0) {
            if ($this->size === 0) {
                $this->errors['required'] = 'File is empty.';
                return false;
            }
            return true;
        }

        switch ($this->error) {
            case 1:
            case 2:
                $this->errors['required'] = 'File is too big (max: ' . self::convertFromBytes($this->maxSize) . ').';
                break;
            case 3:
                $this->errors['required'] = 'File was only partially uploaded.';
                break;
            case 4:
                $this->errors['required'] = 'No file submitted.';
                break;
            default:
                $this->errors['required'] = 'Sorry, there was a problem uploading the file.';
        }
        return false;
    }


    /**
     * Check the file size is below max size.
     */
    public function checkMaxSize()
    {
        $this->checked['checkMaxSize'] = true;

        if ($this->size > $this->maxSize) {
            $this->errors['maxSize'] = 'File exceeds the maximum size (' . self::convertFromBytes($this->maxSize) . ').';
            return false;
        }
        return true;
    }


    /**
     * Check file extension and corrseponding MIME type is in permitted array.
     *
     * @return bool True if file extension and corresponsing MIME type are permitted; false otherwise.
     */
    public function checkType()
    {
        $this->checked['checkType'] = true;

        // Check file extension is in the list of permitted extensions.
        if (!in_array($this->extension, $this->permittedExtensions, true)) {
            $this->errors['type'] = 'Extension must be one of the following: ' . implode(', ', $this->permittedExtensions) . '.';
            return false;
        }
        // Check MIME type is known.
        if (!array_key_exists($this->type, $this->permittedTypes)) {
            $this->errors['type'] = 'MIME type not supported.';
            return false;
        }
        // Check the extension matches a known, valid MIME type.
        if (!in_array($this->extension, $this->permittedTypes[$this->type])) {
            $this->errors['type'] = 'MIME type does not match its extension.';
            return false;
        }
        return true;
    }


    /**
     * Perform various checks to see if the file is fit for upload.
     *
     * @return bool True if file is fit for upload; false otherwise.
     */
    public function validate()
    {
        foreach ($this->checked as $method => $passed) {
            if ($passed) continue;
            if (!$this->$method()) {
                return false;
            }
        }
        return true;
    }


    /**
     * Rename the file if necessary.
     */
    private function rename()
    {
        // Sanitize file name and replace spaces with underscores.
        $this->filterName();

        // Only rename duplicates if overwrite is false.
        if (!$this->overwrite) {
            // Get an array of all files in destination directory.
            $existing = scandir($this->destination);
            // If the file name already exists in the destination directory...
            if (in_array($this->name, $existing, true)) {
                // Get different parts of the file name to be used in renaming.
                $filename = pathinfo($this->name, PATHINFO_FILENAME);
                // Rename file with number until file name is unique.
                $i = 1;
                do {
                    $this->name = $filename . '_' . $i++ . '.' . $this->extension;
                } while (in_array($this->name, $existing, true));
            }
        }
    }


    /**
     * Sanitize file name and replace spaces with underscores.
     */
    private function filterName()
    {
        // Replace spaces with underscores.
        $this->name = str_replace(' ', '_', $this->name);

        // Get filename (without extension) and extension.
        $filename = pathinfo($this->name, PATHINFO_FILENAME);

        // Remove any invalid characters.
        $filename = preg_replace('/[^a-zA-Z0-9_\.-]/', '', $filename);

        // Save filtered file name.
        $this->name = $filename . '.' . $this->extension;
    }


    /**
     * Check file is fit for upload and upload if so.
     *
     * @param string $destination Destination directory to upload file to.
     * @return bool True if file is successfully uploaded; false otherwise.
     */
    public function store(string $destination)
    {
        $this->setDestination($destination);
        $this->rename();
        if ($this->validate()) {
            if ($this->move()) {
                return true;
            } else {
                $this->errors['move'] = 'Could not move file to permanent location.';
            }
        }
        return false;
    }


    /**
     * Save file in destination directory.
     *
     * @return bool True if file was saved successfully; false otherwise.
     */
    private function move()
    {
        return move_uploaded_file($this->tmp_name, $this->destination . $this->name);
    }


    /**
     * Delete uploaded file and, if directory was created to hold it, that too.
     *
     * @return bool True if file (and directory) successfully deleted or doesn't exist; false otherwise.
     */
    public function delete()
    {
        $path = $this->destination . $this->name;
        if (file_exists($path)) {
            if (!unlink($path)) {
                return false;
            }
            if ($this->dirCreated && !rmdir($this->destination)) {
                return false;
            }
        }
        return true;
    }


    /**
     * Convert human readable representation of file size to bytes.
     * E.g. '50K' -> 51200
     *
     * @param string $val Bytes in human-readable format, e.g. '50K'
     * @return float Bytes converted to number of bytes, e.g. 51200.
     */
    public static function convertToBytes(string $val)
    {
        // Trim whitespace.
        $val = trim($val);
        // Get unit, e.g. K for kilobytes.
        $unit = strtoupper($val[strlen($val) - 1]);
        // Convert string to float, which will throw away the unit.
        $val = (float)$val;
        // Multiply value by appropriate multiplier(s).
        switch ($unit) {
            case 'G':
                $val *= 1024;
            case 'M':
                $val *= 1024;
            case 'K':
                $val *= 1024;
        }
        return $val;
    }


    /**
     * Convert bytes to human readable format.
     * E.g. 51200 -> '50K'
     *
     * @param float $bytes Bytes to convert to human-readable format, e.g. 51200
     * @return string Bytes in human-readable format, e.g. '50K'
     */
    public static function convertFromBytes($bytes)
    {
        $bytes /= 1024;
        if ($bytes > 1024 ** 2) {
            return number_format($bytes / 1024 ** 2, 1) . ' GB';
        } elseif ($bytes > 1024) {
            return number_format($bytes / 1024, 1) . ' MB';
        }
        return number_format($bytes, 1) . ' KB';
    }


    /** @var array $uploaded Array of files successfully uploaded with FileUpload::upload(). */
    private static $uploaded = [];

    /** @var array $uploadedErrors Array of errors from files uploaded with FileUpload::upload(). */
    private static $uploadedErrors = [];

    /** @var array $uploadedNames Array of file names uploaded with FileUpload::upload(). */
    private static $uploadedNames = [];


    /**
     * Upload multiple files at once.
     *
     * All files must be successfully stored for this method to
     * return true. If any file fails, all previously stored files
     * are deleted.
     *
     * @param array $files Array of uploaded file names.
     * @param string $destination Destination directory to store files.
     * @param array $options Array of options to set for all files.
     * @return bool True on success; false on failure.
     * @throws \Throwable Re-throws any exceptions after deleting successfully uploaded files.
     */
    public static function upload(array $files, string $destination, array $options = null)
    {
        $request = app('Bellona\Http\Request');
        // Reset arrays containing uploaded files.
        self::$uploaded = [];
        self::$uploadedNames = [];
        self::$uploadedErrors = [];
        try {
            foreach ($files as $name) {
                $file = $request->upload($name);
                if ($options) {
                    $file->setOptions($options);
                }
                if ($file->store($destination)) {
                    array_unshift(self::$uploaded, $file);
                    self::$uploadedNames[$name] = $file->getName();
                } else {
                    self::deleteUploaded();
                    self::$uploadedErrors[$name] = $file->getError();
                    return false;
                }
            }
            return true;
        } catch (\Throwable $e) {
            self::deleteUploaded();
            throw $e;
        }
    }

    /**
     * Delete all files uploaded with FileUpload::upload().
     */
    public static function deleteUploaded()
    {
        foreach (self::$uploaded as $file) {
            $file->delete();
        }
    }


    /**
     * Retrieve name of file stored with FileUpload::upload().
     *
     * @param string $name $_FILES array key for uploaded file.
     * @return string Name with which file was stored.
     */
    public static function getUploadedName(string $name)
    {
        return self::$uploadedNames[$name];
    }

    /**
     * Retrieve array of errors from files uploaded with FileUpload::upload().
     *
     * Keys are keys used to grab files from $_FILES array; values are
     * move error messages.
     *
     * @return array Array of errors.
     */
    public static function getUploadedErrors()
    {
        return self::$uploadedErrors;
    }
}
