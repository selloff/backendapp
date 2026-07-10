<?php

use App\Modules\Selloff\Catalog\Http\Resources\Api\V1\ProductResource;
use App\Modules\Selloff\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->fixture = base_path('tests/fixtures/product-location-import.sql');
    $this->artisan('selloff:migrate', ['--fresh' => true]);
});

test('product location and verified seller are imported', function () {
    $this->artisan('selloff:import-legacy-data', ['--source' => $this->fixture])->assertSuccessful();

    $product = DB::table('products')->where('id', 94001)->first();
    expect($product)->not->toBeNull();
    expect((int) $product->state_id)->toBe(306);
    expect((int) $product->city_id)->toBe(1001);

    $verified = DB::table('vendor_profiles')->where('user_id', 92001)->value('is_verified_seller');
    expect((bool) $verified)->toBeTrue();

    $model = Product::query()
        ->with(ProductResource::listEagerLoads())
        ->findOrFail(94001);

    $payload = (new ProductResource($model))->resolve();

    expect($payload['state_name'])->toBe('Ogun');
    expect($payload['city_name'])->toBe('Abeokuta South');
    expect($payload['vendor']['is_verified_seller'])->toBeTrue();
});