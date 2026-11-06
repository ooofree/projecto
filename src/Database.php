<?php
declare(strict_types=1);

namespace ProjectO;

use PDO;
use RuntimeException;

final class Database
{
    public static function connect(): PDO
    {
        $dsn = getenv('APP_DSN');
        $username = getenv('APP_DB_USER');
        $password = getenv('APP_DB_PASSWORD');

        if ($dsn === false || $dsn === '' || $username === false || $password === false) {
            throw new RuntimeException(
                'Database configuration is missing. Set APP_DSN, APP_DB_USER, and APP_DB_PASSWORD.'
            );
        }

        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            // Store and read every timestamp in UTC; display conversion happens
            // in the application layer using the effective user/project zone.
            $pdo->exec("SET time_zone = '+00:00'");
        }

        return $pdo;
    }
}
