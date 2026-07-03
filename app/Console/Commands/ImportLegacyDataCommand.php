<?php

namespace App\Console\Commands;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportCoverage;
use App\LegacyImport\LegacyImportOrchestrator;
use App\LegacyImport\LegacyImportVerifier;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyImportConfig;
use App\LegacyImport\Support\LegacyImportMemory;
use App\LegacyImport\Support\LegacyImportSchemaGuard;
use App\Modules\Selloff\Admin\Services\SuperAdminPinBootstrap;
use Illuminate\Console\Command;

class ImportLegacyDataCommand extends Command
{
    protected $signature = 'selloff:import-legacy-data
                            {--source= : Path to MySQL dump file}
                            {--dry-run : Report transforms without writing}
                            {--batch-size=1000 : Rows per batch}
                            {--table= : Import a single legacy table only}
                            {--skip-verify : Skip post-import verification}
                            {--profile : Print per-importer timing for Pass 21 profiling}';

    protected $description = 'Import legacy MySQL dump into PostgreSQL (Pass 10/11 ETL)';

    public function handle(
        LegacyImportOrchestrator $orchestrator,
        LegacyImportVerifier $verifier,
        LegacyImportCoverage $coverage,
    ): int {
        LegacyImportMemory::applyConfiguredLimit();

        $importStartedAt = microtime(true);
        $rawSource = (string) ($this->option('source') ?: config('selloff.legacy_import.default_source'));
        $source = $this->resolveReadableDumpPath($rawSource);
        if ($source === '') {
            $this->error('Provide --source=PATH to a readable MySQL dump file.');
            if ($rawSource !== '') {
                $this->line("Could not read: {$rawSource}");
                $this->line('Tip: use an absolute path, or place the dump at docs/data/production-mysql-dump.sql');
                $this->line('      (see docs/data/README.md — symlink target must exist).');
            }

            return self::FAILURE;
        }

        $raisedLimit = LegacyImportMemory::raiseForLargeDump($source);
        if ($raisedLimit !== null) {
            $this->warn("Large dump detected; raised PHP memory_limit to {$raisedLimit}.");
        }

        $dryRun = (bool) $this->option('dry-run');
        $profile = (bool) $this->option('profile');

        if (! $dryRun) {
            $missingTables = LegacyImportSchemaGuard::missingRequiredTables();
            if ($missingTables !== []) {
                $this->error('PostgreSQL schema is incomplete. Run a full migrate before importing legacy data.');
                $this->line('Missing tables: '.implode(', ', $missingTables));
                $this->line('Fix: cd api.selloff && RUN_DEMO_SEEDER=false php artisan selloff:migrate --fresh');

                return self::FAILURE;
            }
        }

        $reader = new MySqlDumpReader($source);
        $context = new LegacyImportContext(
            dryRun: $dryRun,
            batchSize: (int) $this->option('batch-size'),
            tableFilter: $this->option('table') ? (string) $this->option('table') : null,
            profile: $profile,
        );

        $this->info($dryRun ? 'Dry-run legacy import (no writes).' : 'Importing legacy data...');
        $this->line("Source: {$source}");
        $this->line('Tables in dump: '.implode(', ', $reader->tableNames()));

        $orchestrator->run($context, $reader);

        foreach ($context->stats() as $table => $stats) {
            $this->line(sprintf(
                '  %s: planned=%d imported=%d skipped=%d',
                $table,
                $stats['planned'] ?? 0,
                $stats['imported'] ?? 0,
                $stats['skipped'] ?? 0,
            ));
        }

        if ($profile) {
            $this->newLine();
            $this->info('Importer timing (Pass 21 profile):');
            foreach ($context->timings() as $importer => $seconds) {
                $this->line(sprintf('  %s: %.3fs', $importer, $seconds));
            }
            $this->line(sprintf('  total_wall_time: %.3fs', microtime(true) - $importStartedAt));
            $budget = (int) config('selloff.legacy_import.maintenance_window_seconds', 14400);
            if ($budget > 0) {
                $this->line(sprintf('  maintenance_budget: %ds (%.1fh)', $budget, $budget / 3600));
            }
        }

        $unhandled = $coverage->unhandledTables(
            $reader,
            $orchestrator->coveredLegacyTables(),
            LegacyImportConfig::coverageExcludedTables(),
        );

        if ($unhandled !== []) {
            $this->newLine();
            $this->warn('Unhandled legacy tables in dump: '.implode(', ', $unhandled));

            if ($dryRun) {
                return self::FAILURE;
            }
        } elseif ($dryRun) {
            $this->info('Dry-run coverage: all dump tables are handled or explicitly skipped.');
        }

        if ($dryRun || $this->option('skip-verify')) {
            if (! $dryRun) {
                $this->bootstrapSuperAdminPinIfMissing();
            }
            $this->info($dryRun ? 'Dry-run complete.' : 'Import complete (verification skipped).');

            return self::SUCCESS;
        }

        $result = $verifier->verify($reader, importStats: $context->stats());
        foreach ($result->warnings as $warning) {
            $this->warn($warning);
        }

        if (! $result->passed()) {
            foreach ($result->errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $this->info('Import complete; verification passed.');
        $this->bootstrapSuperAdminPinIfMissing();

        return self::SUCCESS;
    }

    private function bootstrapSuperAdminPinIfMissing(): void
    {
        $bootstrap = app(SuperAdminPinBootstrap::class);

        if ($bootstrap->ensureConfigured()) {
            $this->warn('Super Admin PIN was missing and has been bootstrapped (see SUPER_ADMIN_BOOTSTRAP_PIN).');
        }
    }

    private function resolveReadableDumpPath(string $path): string
    {
        if ($path === '') {
            return '';
        }

        foreach ($this->dumpPathCandidates($path) as $candidate) {
            $resolved = realpath($candidate);
            if ($resolved !== false && is_readable($resolved)) {
                return $resolved;
            }
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function dumpPathCandidates(string $path): array
    {
        $candidates = [$path];

        if (! $this->isAbsolutePath($path)) {
            $candidates[] = base_path($path);

            if (str_starts_with($path, '../')) {
                $candidates[] = base_path(substr($path, 3));
            }
        }

        return array_values(array_unique($candidates));
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || (bool) preg_match('#^[A-Za-z]:[/\\\\]#', $path);
    }
}
