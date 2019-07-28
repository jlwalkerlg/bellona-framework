<?php

namespace Bellona\Database;

use PDO;
use PDOException;

class Database
{
    private $dbh;

    public function __construct()
    {
        $host = getenv('DB_HOST');
        $user = getenv('DB_USER');
        $pass = getenv('DB_PASS');
        $name = getenv('DB_NAME');

        $dsn = 'mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8';

        $options = [
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ
        ];

        try {
            $this->dbh = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            exit('Failed to connect to database.');
        }
    }

    public function connection()
    {
        return $this->dbh;
    }
}
