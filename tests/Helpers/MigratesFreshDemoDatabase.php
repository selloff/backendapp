<?php

namespace Tests\Helpers;

trait MigratesFreshDemoDatabase
{
    protected function migrateFreshDemoDatabase(): void
    {
        config(['app.run_demo_seeder' => true]);
        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }
}
