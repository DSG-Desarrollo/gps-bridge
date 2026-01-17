<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static $instance;
    private PDO $connection;

    private function __construct()
    {
        $config = require __DIR__ . '/../../config/database.php';

        $dsn = sprintf(
            "mysql:host=%s;dbname=%s;port=%s;charset=utf8mb4",
            $config['host'],
            $config['database'],
            $config['port']
        );

        try {

            $this->connection = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );

        } catch (PDOException $e) {
            die("Database error: " . $e->getMessage());
        }
    }

    public static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }
}
