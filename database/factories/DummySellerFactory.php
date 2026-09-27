<?php

namespace Database\Factories;

use App\Models\DummySeller;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DummySeller>
 */
class DummySellerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company().' Store';

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->optional()->paragraph(),
            'image' => 'https://picsum.photos/seed/'.Str::slug($name).'/400/400',
            'banner' => 'https://picsum.photos/seed/'.Str::slug($name).'-banner/1200/400',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '08'.fake()->numerify('##########'),
            'city' => fake()->randomElement(['Jakarta', 'Bandung', 'Surabaya', 'Yogyakarta', 'Semarang', 'Medan', 'Makassar', 'Denpasar']),
            'address' => fake()->optional()->streetAddress(),
            'rating' => fake()->randomFloat(2, 3, 5),
            'is_verified' => fake()->boolean(60),
        ];
    }
}
