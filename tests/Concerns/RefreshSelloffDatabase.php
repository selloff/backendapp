<?php

namespace Tests\Concerns;

use Illuminate\Foundation\Testing\RefreshDatabase;

trait RefreshSelloffDatabase
{
    use RefreshDatabase {
        refreshTestDatabase as baseRefreshTestDatabase;
    }

    protected function refreshTestDatabase(): void
    {
        $this->artisan('selloff:migrate', ['--fresh' => true]);

        if ($this->shouldSeed()) {
            $this->artisan('db:seed');
        }
    }
}
