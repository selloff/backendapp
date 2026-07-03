<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Console\Migrations\FreshCommand as LaravelFreshCommand;

class MigrateFreshCommand extends LaravelFreshCommand
{
    public function handle()
    {
        if ($this->isProhibited() || ! $this->confirmToProceed()) {
            return Command::FAILURE;
        }

        if ($this->shouldDelegateToSelloffMigrate()) {
            $this->components->info('Running full schema via selloff:migrate (platform + packages).');
            $this->components->warn('Platform-only refresh: php artisan migrate:fresh --path=database/migrations');

            $params = ['--fresh' => true];

            if ($this->needsSeeding()) {
                $params['--seed'] = true;
                $params['--seed-class'] = $this->option('seeder')
                    ?: 'Database\\Seeders\\DatabaseSeeder';
            }

            return $this->call('selloff:migrate', $params);
        }

        return parent::handle();
    }

    private function shouldDelegateToSelloffMigrate(): bool
    {
        $paths = $this->input->getOption('path');

        if ($paths === null || $paths === [] || $paths === false) {
            return true;
        }

        $normalized = array_map(
            static fn (string $path): string => str_replace('\\', '/', $path),
            (array) $paths,
        );

        return ! $this->isPlatformMigrationsPath($normalized);
    }

    /**
     * @param  list<string>  $paths
     */
    private function isPlatformMigrationsPath(array $paths): bool
    {
        if (count($paths) !== 1) {
            return false;
        }

        $path = $paths[0];

        return in_array($path, [
            'database/migrations',
            base_path('database/migrations'),
        ], true);
    }
}
