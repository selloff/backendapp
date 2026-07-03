<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_slugs', function (Blueprint $table) {
            $table->id();
            $table->string('route_key')->unique();
            $table->string('slug', 100);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestampsTz();
        });

        $defaults = [
            ['route_key' => 'admin', 'slug' => 'admin'],
            ['route_key' => 'blog', 'slug' => 'blog'],
            ['route_key' => 'cart', 'slug' => 'cart'],
            ['route_key' => 'category', 'slug' => 'category'],
            ['route_key' => 'contact', 'slug' => 'contact'],
            ['route_key' => 'dashboard', 'slug' => 'dashboard'],
            ['route_key' => 'forgot_password', 'slug' => 'forgot-password'],
            ['route_key' => 'help_center', 'slug' => 'help-center'],
            ['route_key' => 'messages', 'slug' => 'messages'],
            ['route_key' => 'products', 'slug' => 'products'],
            ['route_key' => 'register', 'slug' => 'register'],
            ['route_key' => 'shops', 'slug' => 'shops'],
            ['route_key' => 'wishlist', 'slug' => 'wishlist'],
        ];

        $now = now();
        DB::table('route_slugs')->insert(array_map(
            fn (array $row) => [...$row, 'created_at' => $now, 'updated_at' => $now],
            $defaults,
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('route_slugs');
    }
};
