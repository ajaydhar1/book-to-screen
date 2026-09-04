<?php

declare(strict_types=1);

interface DatabaseStatement
{
    public function bindValue(
        string|int $param,
        mixed $value,
        int $type = PDO::PARAM_STR
    ): bool;

    public function execute(?array $params = null): bool;

    public function fetch(int $mode = PDO::FETCH_DEFAULT): mixed;

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT): array;

    public function fetchColumn(int $column = 0): mixed;

    public function rowCount(): int;
}