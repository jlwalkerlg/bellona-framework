<?php

namespace Bellona\Encryption;

use Defuse\Crypto\Key;
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException;
use Defuse\Crypto\Exception\BadFormatException;

class Encryptor
{
    /** @var Key $key Key object. */
    private $key;


    /**
     * Load key from .env file or create new key.
     */
    public function __construct()
    {
        $asciiKey = getenv('ENCRYPTION_KEY');

        $this->key = $asciiKey !== false ? $this->loadKey($asciiKey) : $this->generateKey();
    }


    /**
     * Generate a new Key instance and save to .env as ascii string.
     *
     * @return Key
     */
    private function generateKey()
    {
        try {
            $key = Key::createNewRandomKey();
            $asciiKey = $key->saveToAsciiSafeString();
        } catch (EnvironmentIsBrokenException $e) {
            exit('Cannot safely perform encryption.');
        }

        if (!$this->saveKey($asciiKey)) {
            exit('Cannot safely create a key.');
        }

        return $key;
    }


    /**
     * Load Key instance from ascii key.
     *
     * @param string $asciiKey
     * @return Key
     */
    private function loadKey(string $asciiKey)
    {
        try {
            $key = Key::loadFromAsciiSafeString($asciiKey);
        } catch (EnvironmentIsBrokenException $e) {
            exit('Cannot safely perform encryption.');
        } catch (BadFormatException $e) {
            exit('Key is not valid.');
        }
        return $key;
    }


    /**
     * Save ascii key to .env file.
     *
     * @return bool True if successfully saved to file; false otherwise.
     */
    private function saveKey(string $asciiKey)
    {
        $data = "\nENCRYPTION_KEY=\"{$asciiKey}\"\n";
        $pathToEnvFile = PROJECT_ROOT . '/.env';
        return file_put_contents($pathToEnvFile, $data, FILE_APPEND);
    }


    /**
     * Encrypt a plaintext string into cipher text.
     *
     * @param string $plaintext
     * @return string Encrypted (cipher) text.
     */
    public function encryptString(string $plaintext)
    {
        try {
            $ciphertext = Crypto::encrypt($plaintext, $this->key);
        } catch (EnvironmentIsBrokenException $e) {
            exit('Cannot safely perform encryption.');
        }
        return $ciphertext;
    }


    /**
     * Decrypt cipher text into a string.
     *
     * @param string $ciphertext
     * @return string
     */
    public function decryptString(string $ciphertext)
    {
        try {
            $decrypted = Crypto::decrypt($ciphertext, $this->key);
        } catch (EnvironmentIsBrokenException $e) {
            exit('Cannot safely perform encryption.');
        } catch (WrongKeyOrModifiedCiphertextException $e) {
            exit('The cipher text has been tampered with.');
        }
        return $decrypted;
    }


    /**
     * Encrypt data into cipher text.
     *
     * @param mixed $data
     * @return string Encrypted (cipher) text.
     */
    public function encryptData($data)
    {
        $json = json_encode($data);
        return $this->encryptString($json);
    }


    /**
     * Decrypt cipher text into an array.
     *
     * @param string $ciphertext
     * @return array Decrypted data.
     */
    public function decryptData(string $ciphertext)
    {
        $decrypted = $this->decryptString($ciphertext);
        return json_decode($decrypted, true);
    }
}
