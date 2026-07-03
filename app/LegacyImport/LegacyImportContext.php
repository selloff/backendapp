<?php

namespace App\LegacyImport;

class LegacyImportContext
{
    /** @var array<string, array<int, int>> */
    private array $maps = [];

    /** @var array<string, array{planned: int, imported: int, skipped: int}> */
    private array $stats = [];

    /** @var array<string, float> */
    private array $timings = [];

    public function __construct(
        public readonly bool $dryRun = false,
        public readonly int $batchSize = 1000,
        public readonly ?string $tableFilter = null,
        public readonly bool $profile = false,
    ) {}

    public function noteTiming(string $label, float $seconds): void
    {
        $this->timings[$label] = ($this->timings[$label] ?? 0.0) + $seconds;
    }

    /**
     * @return array<string, float>
     */
    public function timings(): array
    {
        return $this->timings;
    }

    public function shouldImportTable(string $legacyTable): bool
    {
        return $this->tableFilter === null || $this->tableFilter === $legacyTable;
    }

    public function notePlanned(string $legacyTable): void
    {
        $this->stats[$legacyTable]['planned'] = ($this->stats[$legacyTable]['planned'] ?? 0) + 1;
    }

    public function noteImported(string $legacyTable): void
    {
        $this->stats[$legacyTable]['imported'] = ($this->stats[$legacyTable]['imported'] ?? 0) + 1;
    }

    public function noteSkipped(string $legacyTable): void
    {
        $this->stats[$legacyTable]['skipped'] = ($this->stats[$legacyTable]['skipped'] ?? 0) + 1;
    }

    public function rememberMap(string $legacyTable, int $legacyId, string $newTable, int $newId): void
    {
        $this->maps[$legacyTable][$legacyId] = $newId;
    }

    public function resolveId(string $legacyTable, mixed $legacyId): ?int
    {
        if ($legacyId === null || $legacyId === '') {
            return null;
        }

        $legacyId = (int) $legacyId;

        return $this->maps[$legacyTable][$legacyId] ?? null;
    }

    /**
     * @return array<string, array{planned: int, imported: int, skipped: int}>
     */
    public function stats(): array
    {
        return $this->stats;
    }
}
