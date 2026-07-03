<?php

use App\LegacyImport\Support\LegacyValueCoercer;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $latest = $this->storedBool('index_latest_products');
        $promoted = $this->storedBool('index_promoted_products');

        if ($latest && $promoted) {
            return;
        }

        if (! $latest && ! $promoted) {
            app(PlatformSettingsService::class)->upsertMany([
                'index_latest_products' => true,
                'index_promoted_products' => true,
            ], 'homepage');

            return;
        }

        $updates = [];
        if (! $latest) {
            $updates['index_latest_products'] = true;
        }
        if (! $promoted) {
            $updates['index_promoted_products'] = true;
        }

        if ($updates !== []) {
            app(PlatformSettingsService::class)->upsertMany($updates, 'homepage');
        }
    }

    public function down(): void
    {
        // Intentionally left blank — do not re-hide homepage products on rollback.
    }

    private function storedBool(string $key): bool
    {
        $row = DB::table('platform_settings')->where('key', $key)->first();
        if ($row === null) {
            return true;
        }

        $decoded = json_decode((string) $row->value, true);

        return LegacyValueCoercer::bool($decoded);
    }
};
