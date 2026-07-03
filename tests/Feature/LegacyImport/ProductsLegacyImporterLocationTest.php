<?php

namespace Tests\Feature\LegacyImport;

use App\Modules\Selloff\Catalog\Http\Resources\Api\V1\ProductResource;
use App\Modules\Selloff\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductsLegacyImporterLocationTest extends TestCase
{
    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = base_path('tests/fixtures/product-location-import.sql');
        $this->artisan('selloff:migrate', ['--fresh' => true]);
    }

    public function test_product_location_and_verified_seller_are_imported(): void
    {
        $this->artisan('selloff:import-legacy-data', ['--source' => $this->fixture])->assertSuccessful();

        $product = DB::table('products')->where('id', 94001)->first();
        $this->assertNotNull($product);
        $this->assertSame(306, (int) $product->state_id);
        $this->assertSame(1001, (int) $product->city_id);

        $verified = DB::table('vendor_profiles')->where('user_id', 92001)->value('is_verified_seller');
        $this->assertTrue((bool) $verified);

        $model = Product::query()
            ->with(ProductResource::listEagerLoads())
            ->findOrFail(94001);

        $payload = (new ProductResource($model))->resolve();

        $this->assertSame('Ogun', $payload['state_name']);
        $this->assertSame('Abeokuta South', $payload['city_name']);
        $this->assertTrue($payload['vendor']['is_verified_seller']);
    }
}
