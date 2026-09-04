<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/LocalSqliteStatement.php';

final class LocalSqliteDatabase implements Database
{
    private PDO $pdo;

    public function __construct(string $databasePath)
    {
        $directory = dirname($databasePath);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $this->pdo = new PDO('sqlite:' . $databasePath);

        $this->pdo->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );

        $this->pdo->exec('PRAGMA foreign_keys = ON;');
    }

    public function prepare(string $sql): DatabaseStatement
    {
        $statement = $this->pdo->prepare($sql);

        return new LocalSqliteStatement($statement);
    }

    public function query(string $sql): DatabaseStatement
    {
        $statement = $this->pdo->query($sql);

        if ($statement === false) {
            throw new RuntimeException('Database query failed.');
        }

        return new LocalSqliteStatement($statement);
    }

    public function exec(string $sql): int|false
    {
        return $this->pdo->exec($sql);
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return $this->pdo->lastInsertId($name);
    }
}