<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/SQLiteCloudStatement.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use SQLiteCloud\SQLiteCloudClient;
use SQLiteCloud\SQLiteCloudRowset;

final class SQLiteCloudDatabase implements Database
{
    private SQLiteCloudClient $client;
    private bool $inTransaction = false;

    public function __construct(string $connectionString)
    {
        $this->client = new SQLiteCloudClient();

        $connected = $this->client->connectWithString($connectionString);

        if ($connected === false) {
            throw new RuntimeException(
                'Could not connect to SQLite Cloud.'
            );
        }
    }

    public function prepare(string $sql): DatabaseStatement
    {
        return new SQLiteCloudStatement($this, $sql);
    }

    public function query(string $sql): DatabaseStatement
    {
        $statement = $this->prepare($sql);
        $statement->execute();

        return $statement;
    }

    public function exec(string $sql): int|false
    {
        $result = $this->executeRaw($sql);

        if ($result === false) {
            return false;
        }

        return $this->changes();
    }

    public function beginTransaction(): bool
    {
        if ($this->inTransaction) {
            return false;
        }

        $result = $this->executeRaw('BEGIN');

        if ($result === false) {
            return false;
        }

        $this->inTransaction = true;

        return true;
    }

    public function commit(): bool
    {
        if (!$this->inTransaction) {
            return false;
        }

        $result = $this->executeRaw('COMMIT');

        if ($result === false) {
            return false;
        }

        $this->inTransaction = false;

        return true;
    }

    public function rollBack(): bool
    {
        if (!$this->inTransaction) {
            return false;
        }

        $result = $this->executeRaw('ROLLBACK');

        if ($result === false) {
            return false;
        }

        $this->inTransaction = false;

        return true;
    }

    public function inTransaction(): bool
    {
        return $this->inTransaction;
    }

    public function lastInsertId(?string $name = null): string|false
    {
        $result = $this->executeRaw(
            'SELECT last_insert_rowid() AS id'
        );

        if (!$result instanceof SQLiteCloudRowset || $result->nrows < 1) {
            return false;
        }

        return (string) $result->value(0, 0);
    }

    public function changes(): int
    {
        $result = $this->executeRaw(
            'SELECT changes() AS affected_rows'
        );

        if (!$result instanceof SQLiteCloudRowset || $result->nrows < 1) {
            return 0;
        }

        return (int) $result->value(0, 0);
    }

    public function executeRaw(string $sql): mixed
    {
        $result = $this->client->execute($sql);

        if ($result === false) {
            throw new RuntimeException(
                'SQLite Cloud query failed.'
            );
        }

        return $result;
    }

    public function quoteValue(
        mixed $value,
        ?int $type = null
    ): string {
        if ($type === PDO::PARAM_NULL || $value === null) {
            return 'NULL';
        }

        if ($type === PDO::PARAM_BOOL) {
            return $value ? '1' : '0';
        }

        if ($type === PDO::PARAM_INT) {
            return (string) (int) $value;
        }

        if ($type === PDO::PARAM_STR) {
            return "'" . str_replace("'", "''", (string) $value) . "'";
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            if (!is_finite($value)) {
                throw new InvalidArgumentException(
                    'Cannot store an infinite or NaN floating-point value.'
                );
            }

            return sprintf('%.17g', $value);
        }

        return "'" . str_replace("'", "''", (string) $value) . "'";
    }

    public function __destruct()
    {
        $this->client->disconnect();
    }
}