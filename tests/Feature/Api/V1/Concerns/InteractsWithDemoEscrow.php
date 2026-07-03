<?php

namespace Tests\Feature\Api\V1\Concerns;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Escrow\Models\EscrowTransaction;
use Laravel\Sanctum\Sanctum;

trait InteractsWithDemoEscrow
{
    protected function demoBuyer(): User
    {
        return User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    }

    protected function demoSeller(): User
    {
        return User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    }

    protected function demoAdmin(): User
    {
        return User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    }

    protected function demoClassifiedProduct(): Product
    {
        return Product::query()->where('sku', 'DEMO-CLASSIFIED-1')->firstOrFail();
    }

    protected function demoEscrowTransaction(): EscrowTransaction
    {
        return EscrowTransaction::query()->where('ref', 'DEMOESCROW1')->firstOrFail();
    }

    protected function actAsDemoBuyer(): User
    {
        $buyer = $this->demoBuyer();
        Sanctum::actingAs($buyer);

        return $buyer;
    }

    protected function actAsDemoAdmin(): User
    {
        $admin = $this->demoAdmin();
        Sanctum::actingAs($admin);

        return $admin;
    }
}
