<?php

namespace Database\Factories;

use App\Modules\Selloff\Catalog\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'slug' => Str::slug($name),
            'status' => true,
            'show_on_main_menu' => true,
            'category_order' => fake()->numberBetween(1, 20),
        ];
    }
}
