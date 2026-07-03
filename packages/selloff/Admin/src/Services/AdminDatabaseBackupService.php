<?php

declare(strict_types=1);

namespace App\Modules\Selloff\Admin\Services;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class AdminDatabaseBackupService
{
    public function download(): StreamedResponse
    {
        abort_unless(DB::connection()->getDriverName() === 'pgsql', 422, 'Database backups require a PostgreSQL connection.');

        $config = DB::connection()->getConfig();
        $filename = 'db_backup-'.now()->format('Y-m-d-His').'.sql';

        return response()->streamDownload(function () use ($config): void {
            $process = new Process($this->buildDumpArguments($config));
            $process->setTimeout(null);

            $password = (string) ($config['password'] ?? '');
            if ($password !== '') {
                $process->setEnv(array_merge($process->getEnv() ?: [], ['PGPASSWORD' => $password]));
            }

            $process->run(function (string $type, string $buffer): void {
                echo $buffer;
            });

            if (! $process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }
        }, $filename, [
            'Content-Type' => 'application/sql; charset=UTF-8',
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    private function buildDumpArguments(array $config): array
    {
        $host = $config['host'] ?? '';
        if (is_array($host)) {
            $host = (string) ($host[0] ?? '');
        }

        $arguments = [
            'pg_dump',
            '--no-owner',
            '--no-acl',
            '--username',
            (string) $config['username'],
            '--dbname',
            (string) $config['database'],
        ];

        if ($host !== '') {
            $arguments[] = '--host';
            $arguments[] = $host;
        }

        $port = (string) ($config['port'] ?? '');
        if ($port !== '') {
            $arguments[] = '--port';
            $arguments[] = $port;
        }

        return $arguments;
    }
}
