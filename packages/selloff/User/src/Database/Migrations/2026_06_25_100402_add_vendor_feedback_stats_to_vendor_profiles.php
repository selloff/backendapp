<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_profiles', function (Blueprint $table): void {
            $table->unsignedInteger('feedback_positive_count')->default(0)->after('commission_rate');
            $table->unsignedInteger('feedback_neutral_count')->default(0)->after('feedback_positive_count');
            $table->unsignedInteger('feedback_negative_count')->default(0)->after('feedback_neutral_count');
            $table->unsignedInteger('feedback_total_count')->default(0)->after('feedback_negative_count');
            $table->decimal('feedback_percent_positive', 5, 2)->default(0)->after('feedback_total_count');
            $table->decimal('feedback_avg_rating', 4, 2)->nullable()->after('feedback_percent_positive');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'feedback_positive_count',
                'feedback_neutral_count',
                'feedback_negative_count',
                'feedback_total_count',
                'feedback_percent_positive',
                'feedback_avg_rating',
            ]);
        });
    }
};
