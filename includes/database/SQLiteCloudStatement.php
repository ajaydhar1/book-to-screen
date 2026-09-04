<?php

declare(strict_types=1);

require_once __DIR__ . '/DatabaseStatement.php';

use SQLiteCloud\SQLiteCloudRowset;

final class SQLiteCloudStatement implements DatabaseStatement
{
    private array $bindings = [];

    private ?SQLiteCloudRowset $rowset = null;

    private int $cursor = 0;

    private int $affectedRows = 0;

    public function __construct(
        private SQLiteCloudDatabase $database,
        private string $sql
    ) {
    }

    public function bindValue(
        string|int $param,
        mixed $value,
        int $type = PDO::PARAM_STR
    ): bool {
        if (is_int($param)) {
            throw new InvalidArgumentException(
                'Positional parameters are not currently supported.'
            );
        }

        if (!str_starts_with($param, ':')) {
            $param = ':' . $param;
        }

        $this->bindings[$param] = [
            'value' => $value,
            'type' => $type,
        ];

        return true;
    }

    public function execute(?array $params = null): bool
    {
        if ($params !== null) {
            foreach ($params as $name => $value) {
                if (is_int($name)) {
                    throw new InvalidArgumentException(
                        'Positional parameters are not currently supported.'
                    );
                }

                if (!str_starts_with($name, ':')) {
                    $name = ':' . $name;
                }

                $this->bindings[$name] = [
                    'value' => $value,
                    'type' => $this->inferPdoType($value),
                ];
            }
        }

        $compiledSql = $this->compileSql();

        $result = $this->database->executeRaw($compiledSql);

        $this->cursor = 0;
        $this->rowset = null;
        $this->affectedRows = 0;

        if ($result instanceof SQLiteCloudRowset) {
            $this->rowset = $result;
        }

        if ($this->isWriteStatement($compiledSql)) {
            $this->affectedRows = $this->database->changes();
        }

        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT): mixed
    {
        if ($this->rowset === null) {
            return false;
        }

        if ($this->cursor >= $this->rowset->nrows) {
            return false;
        }

        $row = $this->buildRow($this->cursor, $mode);

        $this->cursor++;

        return $row;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT): array
    {
        if ($this->rowset === null) {
            return [];
        }

        $rows = [];

        while (($row = $this->fetch($mode)) !== false) {
            $rows[] = $row;
        }

        return $rows;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        if ($this->rowset === null) {
            return false;
        }

        if ($this->cursor >= $this->rowset->nrows) {
            return false;
        }

        if ($column < 0 || $column >= $this->rowset->ncols) {
            return false;
        }

        $value = $this->rowset->value(
            $this->cursor,
            $column
        );

        $this->cursor++;

        return $value;
    }

    public function rowCount(): int
    {
        return $this->affectedRows;
    }

    private function buildRow(int $rowIndex, int $mode): array
    {
        $assoc = [];
        $numeric = [];

        for ($column = 0; $column < $this->rowset->ncols; $column++) {
            $name = (string) $this->rowset->name($column);
            $value = $this->rowset->value($rowIndex, $column);

            $assoc[$name] = $value;
            $numeric[$column] = $value;
        }

        return match ($mode) {
            PDO::FETCH_NUM => $numeric,

            PDO::FETCH_BOTH => $assoc + $numeric,

            PDO::FETCH_ASSOC,
            PDO::FETCH_DEFAULT => $assoc,

            default => throw new InvalidArgumentException(
                'Unsupported fetch mode: ' . $mode
            ),
        };
    }

    private function compileSql(): string
    {
        return preg_replace_callback(
            '/:[A-Za-z_][A-Za-z0-9_]*/',
            function (array $match): string {
                $name = $match[0];

                if (!array_key_exists($name, $this->bindings)) {
                    throw new RuntimeException(
                        'Missing SQL parameter: ' . $name
                    );
                }

                $binding = $this->bindings[$name];

                return $this->database->quoteValue(
                    $binding['value'],
                    $binding['type']
                );
            },
            $this->sql
        );
    }

    private function inferPdoType(mixed $value): int
    {
        return match (true) {
            $value === null => PDO::PARAM_NULL,
            is_bool($value) => PDO::PARAM_BOOL,
            is_int($value) => PDO::PARAM_INT,
            default => PDO::PARAM_STR,
        };
    }

    private function isWriteStatement(string $sql): bool
    {
        $sql = ltrim($sql);

        return preg_match(
            '/^(INSERT|UPDATE|DELETE|REPLACE)\b/i',
            $sql
        ) === 1;
    }
}