<?php

namespace Database\Seeders;

use App\Models\DummyProduct;
use App\Models\DummyProductBrand;
use App\Models\DummyProductCategory;
use Illuminate\Database\Seeder;

class DummyProductSeeder extends Seeder
{
    /**
     * Seed dummy brands, categories and products once.
     */
    public function run(): void
    {
        if (DummyProduct::query()->exists()) {
            return;
        }

        $brands = DummyProductBrand::factory()->count(5)->create();
        $categories = DummyProductCategory::factory()->count(6)->create();

        DummyProduct::factory()
            ->count(30)
            ->sequence(fn () => ['dummy_product_brand_id' => $brands->random()->id])
            ->create()
            ->each(fn (DummyProduct $product) => $product->categories()->attach(
                $categories->random(fake()->numberBetween(1, 2))->pluck('id'),
            ));
    }
}
