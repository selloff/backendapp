<?php

namespace App\LegacyImport;

class MySqlDumpReader
{
    /** @var array<string, list<string>> */
    private array $columns = [];

    /** @var array<string, list<array<string, mixed>>> */
    private array $rows = [];

    public function __construct(
        private readonly string $path,
    ) {
        $this->parseCreateTablesFromFile($path);
        $this->parseInsertsFromFile($path);
    }

    /**
     * @return list<string>
     */
    public function tableNames(): array
    {
        return array_keys($this->columns);
    }

    /**
     * @return list<string>
     */
    public function columns(string $table): array
    {
        return $this->columns[$table] ?? [];
    }

    public function hasTable(string $table): bool
    {
        return isset($this->columns[$table]);
    }

    public function rowCount(string $table): int
    {
        return count($this->rows[$table] ?? []);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rows(string $table): array
    {
        return $this->rows[$table] ?? [];
    }

    private function parseCreateTablesFromFile(string $path): void
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return;
        }

        $table = null;
        $body = '';
        $collecting = false;

        while (($line = fgets($handle)) !== false) {
            if (! $collecting) {
                if (preg_match('/^CREATE TABLE `(\w+)` \(/', $line, $match)) {
                    $table = $match[1];
                    $body = '';
                    $collecting = true;
                }

                continue;
            }

            $body .= $line;

            if (str_contains($line, ') ENGINE=')) {
                $this->columns[$table] = $this->parseCreateTableColumns($body);
                $this->rows[$table] = [];
                $table = null;
                $body = '';
                $collecting = false;
            }
        }

        fclose($handle);
    }

    private function parseInsertsFromFile(string $path): void
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return;
        }

        $table = null;
        /** @var list<string> $columns */
        $columns = [];
        $valuesBuffer = '';
        $collectingValues = false;

        while (($line = fgets($handle)) !== false) {
            if (! $collectingValues) {
                if (preg_match('/^INSERT INTO `(\w+)`(?: \(([^)]+)\))?\s*$/', rtrim($line), $match)) {
                    $table = $match[1];
                    $columns = isset($match[2])
                        ? $this->parseColumnList($match[2])
                        : ($this->columns[$table] ?? []);
                    $valuesBuffer = '';
                    $collectingValues = false;

                    continue;
                }

                if (preg_match('/^INSERT INTO `(\w+)`(?: \(([^)]+)\))?\s+VALUES\s*(.*)$/s', rtrim($line), $match)) {
                    $table = $match[1];
                    $columns = isset($match[2])
                        ? $this->parseColumnList($match[2])
                        : ($this->columns[$table] ?? []);
                    $rest = $match[3];

                    if ($rest !== '') {
                        $valuesBuffer = $rest;
                        if (str_ends_with(rtrim($line), ';')) {
                            $this->consumeInsertValues($table, $columns, $valuesBuffer);
                            $table = null;
                            $columns = [];
                            $valuesBuffer = '';
                        } else {
                            $collectingValues = true;
                        }
                    } else {
                        $collectingValues = true;
                        $valuesBuffer = '';
                    }

                    continue;
                }

                if ($table !== null && preg_match('/^VALUES\s*(.*)$/s', trim($line), $valueMatch)) {
                    $rest = trim($valueMatch[1]);

                    if ($rest === '') {
                        $collectingValues = true;
                        $valuesBuffer = '';
                    } else {
                        $valuesBuffer = $rest;
                        if (str_ends_with(trim($line), ';')) {
                            $this->consumeInsertValues($table, $columns, $valuesBuffer);
                            $table = null;
                            $columns = [];
                            $valuesBuffer = '';
                        } else {
                            $collectingValues = true;
                        }
                    }

                    continue;
                }

                continue;
            }

            $valuesBuffer .= $line;
            if (str_ends_with(trim($line), ';')) {
                $this->consumeInsertValues($table, $columns, $valuesBuffer);
                $table = null;
                $columns = [];
                $valuesBuffer = '';
                $collectingValues = false;
            }
        }

        fclose($handle);
    }

    /**
     * @return list<string>
     */
    private function parseColumnList(string $list): array
    {
        return array_map(static fn (string $col) => trim($col, " `\t\n\r\0\x0B"), explode(',', $list));
    }

    /**
     * @param  list<string>  $columns
     */
    private function consumeInsertValues(string $table, array $columns, string $buffer): void
    {
        if (! isset($this->rows[$table])) {
            $this->rows[$table] = [];
        }

        $buffer = rtrim($buffer);
        if (str_ends_with($buffer, ';')) {
            $buffer = substr($buffer, 0, -1);
        }

        foreach ($this->parseValueGroups($buffer) as $values) {
            $row = [];
            foreach ($columns as $index => $column) {
                $row[$column] = $values[$index] ?? null;
            }
            $this->rows[$table][] = $row;
        }
    }

    /**
     * @return list<string>
     */
    private function parseCreateTableColumns(string $body): array
    {
        $columns = [];

        foreach (explode("\n", $body) as $line) {
            if (preg_match('/^\s+`(\w+)`\s+/', $line, $match)) {
                $columns[] = $match[1];
            }
        }

        return $columns;
    }

    /**
     * @return list<list<mixed>>
     */
    private function parseValueGroups(string $valuesSql): array
    {
        $groups = [];
        $length = strlen($valuesSql);
        $index = 0;

        while ($index < $length) {
            while ($index < $length && ($valuesSql[$index] === ' ' || $valuesSql[$index] === ',' || $valuesSql[$index] === "\n" || $valuesSql[$index] === "\r" || $valuesSql[$index] === "\t")) {
                $index++;
            }

            if ($index >= $length) {
                break;
            }

            if ($valuesSql[$index] !== '(') {
                break;
            }

            [$values, $index] = $this->parseTuple($valuesSql, $index + 1);
            $groups[] = $values;
        }

        return $groups;
    }

    /**
     * @return array{0: list<mixed>, 1: int}
     */
    private function parseTuple(string $sql, int $index): array
    {
        $values = [];
        $current = '';
        $inString = false;
        $escaped = false;
        $length = strlen($sql);

        while ($index < $length) {
            $char = $sql[$index];

            if ($inString) {
                if ($escaped) {
                    $current .= $this->unescapeMysqlCharacter($char);
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === "'") {
                    $inString = false;
                } else {
                    $current .= $char;
                }

                $index++;

                continue;
            }

            if ($char === "'") {
                $inString = true;
                $index++;

                continue;
            }

            if ($char === ')') {
                $values[] = $this->castScalar(trim($current));
                $index++;

                break;
            }

            if ($char === ',') {
                $values[] = $this->castScalar(trim($current));
                $current = '';
                $index++;

                continue;
            }

            $current .= $char;
            $index++;
        }

        return [$values, $index];
    }

    private function unescapeMysqlCharacter(string $char): string
    {
        return match ($char) {
            '0' => "\0",
            'b' => "\x08",
            'n' => "\n",
            'r' => "\r",
            't' => "\t",
            'Z' => "\x1A",
            "'" => "'",
            '"' => '"',
            '\\' => '\\',
            default => $char,
        };
    }

    private function castScalar(string $value): mixed
    {
        if ($value === '' || strcasecmp($value, 'NULL') === 0) {
            return null;
        }

        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return $value;
    }
}
