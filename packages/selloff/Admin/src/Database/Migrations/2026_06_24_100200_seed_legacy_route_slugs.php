<?php

use App\LegacyImport\Data\LegacyRouteSlugs;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (LegacyRouteSlugs::rows() as $row) {
            $existing = DB::table('route_slugs')->where('route_key', $row['route_key'])->first();

            if ($existing) {
                DB::table('route_slugs')->where('id', $existing->id)->update([
                    'slug' => $row['slug'],
                    'legacy_id' => $row['legacy_id'],
                    'updated_at' => $now,
                ]);

                continue;
            }

            DB::table('route_slugs')->insert([
                'route_key' => $row['route_key'],
                'slug' => $row['slug'],
                'legacy_id' => $row['legacy_id'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $keys = array_column(LegacyRouteSlugs::rows(), 'route_key');

        DB::table('route_slugs')
            ->whereIn('route_key', $keys)
            ->whereNotIn('route_key', [
                'admin', 'blog', 'cart', 'category', 'contact', 'dashboard',
                'forgot_password', 'help_center', 'messages', 'products', 'register', 'shops', 'wishlist',
            ])
            ->delete();
    }
};
