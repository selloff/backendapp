<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedbacks', function (Blueprint $table) {
            $table->foreignId('vendor_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->string('feedback_type', 20)->nullable()->after('rating');
            $table->string('title')->nullable()->after('feedback_type');
            $table->string('status', 30)->default('unread')->after('feedback');
        });
    }

    public function down(): void
    {
        Schema::table('feedbacks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vendor_id');
            $table->dropColumn(['feedback_type', 'title', 'status']);
        });
    }
};
