<?php

namespace App\Console\Commands;

use App\LegacyImport\LegacyImportVerifier;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyImportMemory;
use App\LegacyImport\Support\LegacyImportSchemaGuard;
use Illuminate\Console\Command;

class VerifyLegacyImportCommand extends Command
{
    protected $signature = 'selloff:verify-legacy-import
                            {--source= : Optional MySQL dump for row-count and revenue checks}
                            {--check-images= : Sample N product image URLs (HTTP check; skips in testing env)}';

    protected $description = 'Verify legacy import row counts, FK integrity, and revenue totals';

    public function handle(LegacyImportVerifier $verifier): int
    {
        LegacyImportMemory::applyConfiguredLimit();

        $source = $this->option('source');
        $reader = null;

        if ($source) {
            $resolved = $this->resolveReadableDumpPath((string) $source);
            if ($resolved === '') {
                $this->error('Source dump not found.');
                $this->line("Could not read: {$source}");
                $this->line('Tip: use an absolute path, or place the dump at docs/data/production-mysql-dump.sql');

                return self::FAILURE;
            }

            $raisedLimit = LegacyImportMemory::raiseForLargeDump($resolved);
            if ($raisedLimit !== null) {
                $this->warn("Large dump detected; raised PHP memory_limit to {$raisedLimit}.");
            }

            $reader = new MySqlDumpReader($resolved);
            $this->line('Comparing against dump: '.$resolved);
        }

        $imageSample = $this->option('check-images');
        $imageUrlSampleSize = $imageSample !== null && $imageSample !== ''
            ? (int) $imageSample
            : (int) config('selloff.legacy_import.verify_image_url_sample_size', 0);

        if ($imageUrlSampleSize > 0 && ! app()->environment('testing')) {
            $this->line("Checking up to {$imageUrlSampleSize} remote product image URLs...");
        }

        $missingTables = LegacyImportSchemaGuard::missingRequiredTables();
        if ($missingTables !== []) {
            $this->error('PostgreSQL schema is incomplete. Run a full migrate before verifying legacy import.');
            $this->line('Missing tables: '.implode(', ', $missingTables));
            $this->line('Fix: cd api.selloff && RUN_DEMO_SEEDER=false php artisan selloff:migrate --fresh');

            return self::FAILURE;
        }

        $result = $verifier->verify($reader, $imageUrlSampleSize);

        foreach ($result->warnings as $warning) {
            $this->warn($warning);
        }

        if ($result->passed()) {
            $this->info('Legacy import verification passed.');

            return self::SUCCESS;
        }

        foreach ($result->errors as $error) {
            $this->error($error);
        }

        return self::FAILURE;
    }

    private function resolveReadableDumpPath(string $path): string
    {
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
