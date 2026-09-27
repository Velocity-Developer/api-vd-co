<?php

namespace Database\Factories;

use App\Models\DummyProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DummyProductCategory>
 */
class DummyProductCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucwords(fake()->unique()->words(2, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->optional()->sentence(),
            'image' => 'https://picsum.photos/seed/'.Str::slug($name).'/400/400',
        ];
    }
}
