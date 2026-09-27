<?php

namespace Database\Seeders;

use App\Models\DummyProduct;
use App\Models\DummyProductBrand;
use App\Models\DummyProductCategory;
use App\Models\DummyProductImage;
use App\Models\DummySeller;
use Illuminate\Database\Seeder;

class DummyProductSeeder extends Seeder
{
    /**
     * Seed dummy brands, categories, sellers and products with gallery pictures once.
     */
    public function run(): void
    {
        if (DummyProduct::query()->exists()) {
            return;
        }

        $brands = DummyProductBrand::factory()->count(5)->create();
        $categories = DummyProductCategory::factory()->count(6)->create();
        $sellers = DummySeller::factory()->count(4)->create();

        DummyProduct::factory()
            ->count(30)
            ->sequence(fn () => [
                'dummy_product_brand_id' => $brands->random()->id,
                'dummy_seller_id' => $sellers->random()->id,
            ])
            ->create()
            ->each(function (DummyProduct $product) use ($categories): void {
                $product->categories()->attach($categories->random(fake()->numberBetween(1, 2))->pluck('id'));

                DummyProductImage::factory()
                    ->count(fake()->numberBetween(2, 4))
                    ->sequence(fn ($sequence) => ['sort_order' => $sequence->index])
                    ->for($product, 'product')
                    ->create();
            });
    }
}
