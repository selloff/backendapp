<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Selloff\Admin\Services\SuperAdminPinBootstrap;
use App\Services\Auth\RolePermissionSync;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class EssentialSeeder extends Seeder
{
    public function run(): void
    {
        app(RolePermissionSync::class)->sync();

        $superAdmin = User::firstOrCreate(
            ['email' => config('app.superadmin_email', 'superadmin@selloff.test')],
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'slug' => 'super-admin',
                'password' => Hash::make(config('app.superadmin_password', 'password')),
                'is_enable_login' => true,
                'is_disable' => false,
                'email_verified_at' => now(),
            ],
        );

        $superAdmin->syncRoles(['super-admin']);

        app(PlatformSettingsService::class)->upsertMany([
            'site_name' => config('app.name', 'Selloff'),
            'site_description' => 'Multi-vendor marketplace',
            'primary_color' => '#0075bb',
            'site_logo_url' => '/selloff-logo.png',
            'site_favicon_url' => '/favicon.svg',
            'about_footer' => 'Selloff is a multi-vendor marketplace for buyers and sellers.',
            'copyright' => '© Selloff. All rights reserved.',
            'multi_vendor_system' => true,
        ], 'security');

        app(SuperAdminPinBootstrap::class)->ensureConfigured('196001');
    }
}
