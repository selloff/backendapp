<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedbacks', function (Blueprint $table): void {
            $table->string('moderation_status', 20)->default('pending')->after('status');
            $table->string('image_path')->nullable()->after('feedback');
            $table->string('image_disk', 30)->nullable()->after('image_path');
            $table->timestampTz('edited_at')->nullable()->after('image_disk');
            $table->timestampTz('approved_at')->nullable()->after('edited_at');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable()->after('approved_by');
        });

        DB::table('feedbacks')->update(['moderation_status' => 'approved']);

        Schema::table('feedbacks', function (Blueprint $table): void {
            $table->unique(['vendor_id', 'user_id'], 'feedbacks_vendor_user_unique');
        });

        Schema::create('feedback_replies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('feedback_id')->constrained('feedbacks')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('author_role', 20);
            $table->text('body');
            $table->timestampsTz();

            $table->unique(['feedback_id', 'author_role'], 'feedback_replies_feedback_role_unique');
        });

        Schema::create('feedback_disputes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('feedback_id')->unique()->constrained('feedbacks')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('users')->cascadeOnDelete();
            $table->text('reason');
            $table->string('status', 20)->default('open');
            $table->text('admin_note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_disputes');
        Schema::dropIfExists('feedback_replies');

        Schema::table('feedbacks', function (Blueprint $table): void {
            $table->dropUnique('feedbacks_vendor_user_unique');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn([
                'moderation_status',
                'image_path',
                'image_disk',
                'edited_at',
                'approved_at',
                'rejection_reason',
            ]);
        });
    }
};
