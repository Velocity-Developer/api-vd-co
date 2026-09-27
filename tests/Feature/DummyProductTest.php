<?php

use App\Models\DummyProduct;
use App\Models\DummyProductBrand;
use App\Models\DummyProductCategory;
use Database\Seeders\DummyProductSeeder;
use Illuminate\Support\Facades\Schema;

test('dummy product tables have the expected columns', function () {
    expect(Schema::hasColumns('dummy_products', [
        'id', 'title', 'description', 'price', 'price_discount', 'rating', 'stock',
        'weight', 'sku', 'image', 'dummy_product_brand_id', 'created_at', 'updated_at',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('dummy_product_brands', ['id', 'name', 'slug', 'description', 'image']))->toBeTrue()
        ->and(Schema::hasColumns('dummy_product_categories', ['id', 'name', 'slug', 'description', 'image']))->toBeTrue()
        ->and(Schema::hasColumns('dummy_product_dummy_product_category', [
            'dummy_product_id', 'dummy_product_category_id', 'created_at', 'updated_at',
        ]))->toBeTrue();
});

test('dummy products belong to a brand and many categories', function () {
    $brand = DummyProductBrand::factory()->create();
    $categories = DummyProductCategory::factory()->count(2)->create();
    $product = DummyProduct::factory()->for($brand, 'brand')->create([
        'price' => 150000,
        'price_discount' => 120000,
        'rating' => 4.5,
        'stock' => 12,
        'weight' => 250.5,
    ]);

    $product->categories()->attach($categories);

    expect($product->fresh())
        ->brand->id->toBe($brand->id)
        ->categories->pluck('id')->sort()->values()->all()->toBe($categories->pluck('id')->sort()->values()->all())
        ->price->toBe('150000.00')
        ->price_discount->toBe('120000.00')
        ->rating->toBe('4.50')
        ->stock->toBe(12)
        ->weight->toBe('250.50')
        ->and($brand->products()->count())->toBe(1)
        ->and($categories->first()->products()->first()->id)->toBe($product->id);
});

test('deleting a brand keeps its products and deleting a category only detaches it', function () {
    $brand = DummyProductBrand::factory()->create();
    $category = DummyProductCategory::factory()->create();
    $product = DummyProduct::factory()->for($brand, 'brand')->create();
    $product->categories()->attach($category);

    $brand->delete();
    $category->delete();

    expect($product->fresh())
        ->not->toBeNull()
        ->dummy_product_brand_id->toBeNull()
        ->and($product->categories()->count())->toBe(0);
});

test('deleting a product removes its category links', function () {
    $category = DummyProductCategory::factory()->create();
    $product = DummyProduct::factory()->create();
    $product->categories()->attach($category);

    $product->delete();

    $this->assertDatabaseMissing('dummy_product_dummy_product_category', ['dummy_product_category_id' => $category->id]);
    $this->assertModelExists($category);
});

test('dummy product seeder creates brands, categories and products once', function () {
    $this->seed(DummyProductSeeder::class);
    $this->seed(DummyProductSeeder::class);

    expect(DummyProductBrand::count())->toBe(5)
        ->and(DummyProductCategory::count())->toBe(6)
        ->and(DummyProduct::count())->toBe(30)
        ->and(DummyProduct::whereNull('dummy_product_brand_id')->count())->toBe(0)
        ->and(DummyProduct::doesntHave('categories')->count())->toBe(0)
        ->and(DummyProduct::doesntHave('images')->count())->toBe(0);
});
