<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\InteractsWithAdminPin;

abstract class TestCase extends BaseTestCase
{
    use InteractsWithAdminPin;

    protected function setUp(): void
    {
        putenv('RUN_DEMO_SEEDER=true');
        $_ENV['RUN_DEMO_SEEDER'] = 'true';
        $_SERVER['RUN_DEMO_SEEDER'] = 'true';

        parent::setUp();

        config(['app.run_demo_seeder' => true]);
    }
}
