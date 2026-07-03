<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(EssentialSeeder::class);

        if (config('app.run_demo_seeder')) {
            $this->call(DemoSeeder::class);
        }
    }
}
