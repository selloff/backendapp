<?php

namespace App\Actions\Health;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CheckHealthAction
{
    /**
     * @return array{status: string, database: bool, storage: bool, storage_disk: string}
     */
    public function execute(): array
    {
        $database = $this->checkDatabase();
        $disk = (string) config('selloff.media_disk', 'public');
        $storage = $this->checkStorage($disk);

        return [
            'status' => $database && $storage ? 'ok' : 'degraded',
            'database' => $database,
            'storage' => $storage,
            'storage_disk' => $disk,
        ];
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function checkStorage(string $disk): bool
    {
        try {
            $probe = '.health-probe';
            Storage::disk($disk)->put($probe, '1');
            Storage::disk($disk)->delete($probe);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
