<?php

use App\Modules\Selloff\Admin\Services\SuperAdminPinBootstrap;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(SuperAdminPinBootstrap::class)->ensureConfigured();
    }

    public function down(): void
    {
        //
    }
};
