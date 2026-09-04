<?php

declare(strict_types=1);

interface Database
{
    public function prepare(string $sql): DatabaseStatement;

    public function query(string $sql): DatabaseStatement;

    public function exec(string $sql): int|false;

    public function beginTransaction(): bool;

    public function commit(): bool;

    public function rollBack(): bool;

    public function inTransaction(): bool;

    public function lastInsertId(?string $name = null): string|false;
}