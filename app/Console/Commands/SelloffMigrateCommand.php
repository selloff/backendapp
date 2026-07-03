<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class SelloffMigrateCommand extends Command
{
    protected $signature = 'selloff:migrate
                            {--fresh : Drop all tables and re-run}
                            {--seed : Run seeders after migrate}
                            {--seed-class=Database\\Seeders\\DatabaseSeeder : Seeder class when --seed is set}
                            {--modules= : Comma-separated module names (default: all in order)}';

    protected $description = 'Run platform migrations then Selloff package migrations in FK-safe module order';

    /**
     * @return list<string>
     */
    private function moduleOrder(): array
    {
        return config('selloff_migrate.module_order', []);
    }

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $this->call('migrate:fresh', [
                '--path' => 'database/migrations',
                '--force' => true,
            ]);
        } else {
            $this->call('migrate', [
                '--path' => 'database/migrations',
                '--force' => true,
            ]);
        }

        $modules = $this->option('modules')
            ? array_map('trim', explode(',', (string) $this->option('modules')))
            : $this->moduleOrder();

        foreach ($modules as $module) {
            $path = "packages/selloff/{$module}/src/Database/Migrations";
            if (! File::isDirectory(base_path($path))) {
                $this->warn("Skipping {$module}: no migrations directory.");

                continue;
            }
            $this->info("Migrating {$module}...");
            try {
                $this->call('migrate', [
                    '--path' => $path,
                    '--force' => true,
                ]);
            } catch (\Throwable $e) {
                $this->error("Module {$module} failed: ".$e->getMessage());
                if (! $this->option('modules')) {
                    throw $e;
                }
            }
        }

        if ($this->option('seed')) {
            $this->call('db:seed', [
                '--class' => $this->option('seed-class'),
                '--force' => true,
            ]);
        }

        $this->info('Selloff migrate complete.');

        return self::SUCCESS;
    }
}
