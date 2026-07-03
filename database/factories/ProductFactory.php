<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $title = fake()->words(3, true);

        return [
            'vendor_id' => User::factory(),
            'category_id' => Category::factory(),
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(4)),
            'sku' => strtoupper(Str::random(8)),
            'type' => 'physical',
            'listing_type' => 'sell_on_site',
            'status' => 'published',
            'visibility' => 'visible',
            'is_active' => true,
            'is_verified' => true,
            'price' => fake()->randomFloat(2, 1000, 250000),
            'currency_code' => 'NGN',
            'stock' => fake()->numberBetween(1, 50),
        ];
    }
}
