<?php

declare(strict_types=1);

namespace App\Database;

final class Connection
{
    private static ?\PDO $instance = null;

    public static function get(): \PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $host   = $_ENV['DB_HOST']     ?? 'localhost';
        $port   = $_ENV['DB_PORT']     ?? '5432';
        $dbname = $_ENV['DB_NAME']     ?? '';
        $user   = $_ENV['DB_USER']     ?? '';
        $pass   = $_ENV['DB_PASSWORD'] ?? '';

        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};options='--client_encoding=UTF8'";

        self::$instance = new \PDO($dsn, $user, $pass, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return self::$instance;
    }

    private function __construct() {}
}