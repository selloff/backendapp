<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('payout_requests')->where('status', 'completed')->update(['status' => 'approved']);
    }

    public function down(): void
    {
        // Status normalization is not reversed.
    }
};
