<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sliders')) {
            return;
        }

        Schema::table('sliders', function (Blueprint $table): void {
            if (! Schema::hasColumn('sliders', 'description')) {
                $table->string('description', 1000)->nullable()->after('title');
            }
            if (! Schema::hasColumn('sliders', 'lang_id')) {
                $table->unsignedTinyInteger('lang_id')->default(1)->after('description');
            }
            if (! Schema::hasColumn('sliders', 'button_text')) {
                $table->string('button_text')->nullable()->after('link');
            }
            if (! Schema::hasColumn('sliders', 'text_color')) {
                $table->string('text_color', 30)->default('#ffffff')->after('button_text');
            }
            if (! Schema::hasColumn('sliders', 'button_color')) {
                $table->string('button_color', 30)->default('#222222')->after('text_color');
            }
            if (! Schema::hasColumn('sliders', 'button_text_color')) {
                $table->string('button_text_color', 30)->default('#ffffff')->after('button_color');
            }
            if (! Schema::hasColumn('sliders', 'animation_title')) {
                $table->string('animation_title', 50)->default('fadeInUp')->after('button_text_color');
            }
            if (! Schema::hasColumn('sliders', 'animation_description')) {
                $table->string('animation_description', 50)->default('fadeInUp')->after('animation_title');
            }
            if (! Schema::hasColumn('sliders', 'animation_button')) {
                $table->string('animation_button', 50)->default('fadeInUp')->after('animation_description');
            }
            if (! Schema::hasColumn('sliders', 'image_mobile_path')) {
                $table->string('image_mobile_path', 500)->nullable()->after('image_path');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sliders')) {
            return;
        }

        Schema::table('sliders', function (Blueprint $table): void {
            $columns = [
                'description',
                'lang_id',
                'button_text',
                'text_color',
                'button_color',
                'button_text_color',
                'animation_title',
                'animation_description',
                'animation_button',
                'image_mobile_path',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('sliders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
