<?php

declare(strict_types=1);

namespace App\Database;

final class DB
{
    public static function connection(): \PDO
    {
        return Connection::get();
    }

    /**
     * Executa SELECT e retorna todos os registros.
     * Ex: DB::query("SELECT * FROM users WHERE ativo = :ativo", ['ativo' => true])
     */
    public static function query(string $sql, array $params = []): array
    {
        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Retorna apenas o primeiro registro ou null.
     */
    public static function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Executa INSERT, UPDATE ou DELETE. Retorna linhas afetadas.
     */
    public static function execute(string $sql, array $params = []): int
    {
        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Retorna o último ID inserido.
     */
    public static function lastInsertId(string $sequence = ''): string
    {
        return Connection::get()->lastInsertId($sequence ?: null);
    }

    private function __construct() {}
}