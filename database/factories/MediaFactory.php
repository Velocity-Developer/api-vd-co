<?php

namespace Database\Factories;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fileName = Str::random(40).'.jpg';

        return [
            'collection' => 'default',
            'disk' => 'public',
            'path' => 'media/'.now()->format('Y/m').'/'.$fileName,
            'original_name' => fake()->slug(3).'.jpg',
            'file_name' => $fileName,
            'extension' => 'jpg',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(10_000, 2_000_000),
            'title' => fake()->sentence(3),
            'alt_text' => fake()->sentence(4),
        ];
    }

    /**
     * Indicate that the media is a PDF document.
     */
    public function document(): static
    {
        return $this->state(function (): array {
            $fileName = Str::random(40).'.pdf';

            return [
                'path' => 'media/'.now()->format('Y/m').'/'.$fileName,
                'original_name' => fake()->slug(3).'.pdf',
                'file_name' => $fileName,
                'extension' => 'pdf',
                'mime_type' => 'application/pdf',
            ];
        });
    }
}
