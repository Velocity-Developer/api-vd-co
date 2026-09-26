<?php

namespace Database\Factories;

use App\Models\DummyProduct;
use App\Models\DummyProductBrand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DummyProduct>
 */
class DummyProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = fake()->numberBetween(10, 2_000) * 1_000;

        return [
            'title' => ucwords(fake()->words(3, true)),
            'description' => fake()->paragraph(),
            'price' => $price,
            'price_discount' => fake()->boolean(40) ? $price - fake()->numberBetween(1, 30) * $price / 100 : null,
            'rating' => fake()->randomFloat(2, 1, 5),
            'stock' => fake()->numberBetween(0, 500),
            'weight' => fake()->randomFloat(2, 50, 5_000),
            'sku' => strtoupper(fake()->unique()->bothify('DP-####-????')),
            'image' => 'https://picsum.photos/seed/'.fake()->unique()->slug(2).'/600/600',
            'dummy_product_brand_id' => DummyProductBrand::factory(),
        ];
    }
}
