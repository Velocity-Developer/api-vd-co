<?php

namespace Database\Factories;

use App\Models\DummyProduct;
use App\Models\DummyProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DummyProductImage>
 */
class DummyProductImageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dummy_product_id' => DummyProduct::factory(),
            'path' => 'https://picsum.photos/seed/'.fake()->unique()->slug(2).'/800/800',
            'sort_order' => 0,
        ];
    }
}
