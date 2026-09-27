<?php

use App\Models\DummyProduct;
use App\Models\DummyProductBrand;
use App\Models\DummyProductCategory;
use App\Models\DummyProductImage;
use App\Models\DummySeller;

function dummyProductApiHeaders(): array
{
    return ['signature' => md5(now()->format('dmY'))];
}

test('v1 dummy product APIs require a valid signature header', function (string $uri) {
    $this->getJson($uri)->assertUnauthorized();
    $this->getJson($uri, ['signature' => 'salah'])->assertForbidden();
})->with(['/api/v1/dummy-products', '/api/v1/dummy-product-brands', '/api/v1/dummy-product-categories']);

test('v1 dummy products API lists products newest first with brand, categories and gallery', function () {
    $brand = DummyProductBrand::factory()->create(['name' => 'Nusantara', 'slug' => 'nusantara', 'image' => 'dummy-product-brands/2026/09/logo.png']);
    $category = DummyProductCategory::factory()->create(['name' => 'Kaos', 'slug' => 'kaos', 'image' => 'https://picsum.photos/seed/kaos/400/400']);
    $older = DummyProduct::factory()->create(['created_at' => now()->subDay()]);
    $newer = DummyProduct::factory()->for($brand, 'brand')->create([
        'title' => 'Kaos Polos',
        'price' => 150000,
        'price_discount' => 120000,
        'image' => 'dummy-products/2026/09/kaos.jpg',
    ]);
    $newer->categories()->attach($category);
    DummyProductImage::factory()->for($newer, 'product')->create(['path' => 'dummy-products/gallery/2026/09/a.jpg', 'sort_order' => 1]);
    DummyProductImage::factory()->for($newer, 'product')->create(['path' => 'https://picsum.photos/seed/b/800/800', 'sort_order' => 0]);

    $this->getJson('/api/v1/dummy-products', dummyProductApiHeaders())
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.per_page', 15)
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.0.price', '150000.00')
        ->assertJsonPath('data.0.price_discount', '120000.00')
        ->assertJsonPath('data.0.image_url', asset('storage/dummy-products/2026/09/kaos.jpg'))
        ->assertJsonPath('data.0.brand.slug', 'nusantara')
        ->assertJsonPath('data.0.brand.image_url', asset('storage/dummy-product-brands/2026/09/logo.png'))
        ->assertJsonPath('data.0.categories.0.slug', 'kaos')
        ->assertJsonPath('data.0.categories.0.image_url', 'https://picsum.photos/seed/kaos/400/400')
        ->assertJsonPath('data.0.gallery.0.url', 'https://picsum.photos/seed/b/800/800')
        ->assertJsonPath('data.0.gallery.1.url', asset('storage/dummy-products/gallery/2026/09/a.jpg'))
        ->assertJsonPath('data.1.id', $older->id);
});

test('v1 dummy products API filters by search, brand slug and category slug', function () {
    $nusantara = DummyProductBrand::factory()->create(['slug' => 'nusantara']);
    $kaos = DummyProductCategory::factory()->create(['slug' => 'kaos']);

    DummyProduct::factory()->for($nusantara, 'brand')->create(['title' => 'Kaos Polos', 'sku' => 'KP-1'])
        ->categories()->attach($kaos);
    DummyProduct::factory()->for($nusantara, 'brand')->create(['title' => 'Topi', 'sku' => 'TP-1']);
    DummyProduct::factory()->create(['title' => 'Kaos Lain', 'sku' => 'KL-1']);

    $titles = fn (string $query): array => collect(
        $this->getJson("/api/v1/dummy-products?{$query}", dummyProductApiHeaders())->assertOk()->json('data'),
    )->pluck('title')->sort()->values()->all();

    expect($titles('search=kaos'))->toBe(['Kaos Lain', 'Kaos Polos'])
        ->and($titles('search=TP-1'))->toBe(['Topi'])
        ->and($titles('brand=nusantara'))->toBe(['Kaos Polos', 'Topi'])
        ->and($titles('category=kaos'))->toBe(['Kaos Polos'])
        ->and($titles('brand=nusantara&category=kaos'))->toBe(['Kaos Polos'])
        ->and($titles('brand=tidak-ada'))->toBe([]);

    $this->getJson('/api/v1/dummy-products?per_page=2&page=2', dummyProductApiHeaders())
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.last_page', 2);

    $this->getJson('/api/v1/dummy-products?per_page=500', dummyProductApiHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['per_page']);
});

test('v1 dummy product brand and category APIs list terms by name with product counts', function (string $uri, string $model) {
    $zeta = $model::factory()->create(['name' => 'Zeta', 'slug' => 'zeta', 'image' => 'folder/zeta.png']);
    $model::factory()->create(['name' => 'Alfa', 'slug' => 'alfa']);

    $products = DummyProduct::factory()->count(2)->create(['dummy_product_brand_id' => null]);

    if ($model === DummyProductBrand::class) {
        $products->each(fn (DummyProduct $product) => $product->update(['dummy_product_brand_id' => $zeta->id]));
    } else {
        $products->each(fn (DummyProduct $product) => $product->categories()->attach($zeta));
    }

    $this->getJson($uri, dummyProductApiHeaders())
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.0.slug', 'alfa')
        ->assertJsonPath('data.0.products_count', 0)
        ->assertJsonPath('data.1.slug', 'zeta')
        ->assertJsonPath('data.1.image_url', asset('storage/folder/zeta.png'))
        ->assertJsonPath('data.1.products_count', 2);

    $this->getJson("{$uri}?search=zet", dummyProductApiHeaders())
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'zeta');
})->with([
    'brands' => ['/api/v1/dummy-product-brands', DummyProductBrand::class],
    'categories' => ['/api/v1/dummy-product-categories', DummyProductCategory::class],
]);

test('v1 dummy sellers API lists sellers and products can be filtered by seller slug', function () {
    $this->getJson('/api/v1/dummy-sellers')->assertUnauthorized();

    $zeta = DummySeller::factory()->create(['name' => 'Zeta Store', 'slug' => 'zeta', 'city' => 'Medan', 'is_verified' => true, 'image' => 'dummy-sellers/2026/09/zeta.png', 'banner' => 'https://picsum.photos/seed/zeta/1200/400']);
    DummySeller::factory()->create(['name' => 'Alfa Store', 'slug' => 'alfa', 'city' => 'Jakarta', 'is_verified' => false]);
    DummyProduct::factory()->create(['title' => 'Barang Zeta', 'dummy_seller_id' => $zeta->id]);
    DummyProduct::factory()->create(['title' => 'Barang Lain', 'dummy_seller_id' => null]);

    $this->getJson('/api/v1/dummy-sellers', dummyProductApiHeaders())
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.slug', 'alfa')
        ->assertJsonPath('data.1.slug', 'zeta')
        ->assertJsonPath('data.1.products_count', 1)
        ->assertJsonPath('data.1.image_url', asset('storage/dummy-sellers/2026/09/zeta.png'))
        ->assertJsonPath('data.1.banner_url', 'https://picsum.photos/seed/zeta/1200/400');

    $this->getJson('/api/v1/dummy-sellers?verified=1', dummyProductApiHeaders())->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'zeta');
    $this->getJson('/api/v1/dummy-sellers?city=Jakarta', dummyProductApiHeaders())->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'alfa');

    $this->getJson('/api/v1/dummy-products?seller=zeta', dummyProductApiHeaders())
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Barang Zeta')
        ->assertJsonPath('data.0.seller.slug', 'zeta')
        ->assertJsonPath('data.0.seller.is_verified', true);
});
