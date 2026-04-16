<?php

declare(strict_types=1);

namespace Core;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $instance = null;

    public static function connection(array $config): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            (int) $config['port'],
            $config['name'],
            $config['charset']
        );

        try {
            self::$instance = new PDO($dsn, $config['user'], $config['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            exit('Database connection failed.');
        }

        return self::$instance;
    }
}
