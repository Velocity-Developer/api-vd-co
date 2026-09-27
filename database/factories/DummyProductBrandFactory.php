<?php

namespace Database\Factories;

use App\Models\DummyProductBrand;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DummyProductBrand>
 */
class DummyProductBrandFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->optional()->sentence(),
            'image' => 'https://picsum.photos/seed/'.Str::slug($name).'/400/400',
        ];
    }
}
