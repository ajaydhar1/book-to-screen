<?php

declare(strict_types=1);

require_once __DIR__ . '/DatabaseStatement.php';

final class LocalSqliteStatement implements DatabaseStatement
{
    public function __construct(
        private PDOStatement $statement
    ) {}

    public function bindValue(
        string|int $param,
        mixed $value,
        int $type = PDO::PARAM_STR
    ): bool {
        return $this->statement->bindValue($param, $value, $type);
    }

    public function execute(?array $params = null): bool
    {
        return $this->statement->execute($params);
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT): mixed
    {
        return $this->statement->fetch($mode);
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT): array
    {
        return $this->statement->fetchAll($mode);
    }

    public function fetchColumn(int $column = 0): mixed
    {
        return $this->statement->fetchColumn($column);
    }

    public function rowCount(): int
    {
        return $this->statement->rowCount();
    }
}
