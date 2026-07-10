<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_jobs', function (Blueprint $table) {
            $table->string('email_type', 60)->nullable()->after('to_email');
            $table->string('template', 120)->nullable()->after('body');
            $table->jsonb('template_data')->nullable()->after('template');
            $table->timestampTz('scheduled_at')->nullable()->after('status');
            $table->unsignedSmallInteger('attempts')->default(0)->after('scheduled_at');
            $table->text('last_error')->nullable()->after('attempts');
            $table->timestampTz('skipped_at')->nullable()->after('last_error');

            $table->index(['status', 'scheduled_at']);
            $table->index('email_type');
        });
    }

    public function down(): void
    {
        Schema::table('email_jobs', function (Blueprint $table) {
            $table->dropIndex(['status', 'scheduled_at']);
            $table->dropIndex(['email_type']);

            $table->dropColumn([
                'email_type',
                'template',
                'template_data',
                'scheduled_at',
                'attempts',
                'last_error',
                'skipped_at',
            ]);
        });
    }
};
