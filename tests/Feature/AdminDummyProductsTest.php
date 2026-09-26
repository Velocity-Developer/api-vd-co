<?php

use App\Models\DummyProduct;
use App\Models\DummyProductBrand;
use App\Models\DummyProductCategory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot manage dummy products', function () {
    $this->get(route('dummy-products'))->assertRedirect(route('login'));
    $this->post('/ajax/dummy-products', ['title' => 'X'])->assertRedirect(route('login'));
    $this->post('/ajax/dummy-product-brands', ['name' => 'X', 'slug' => 'x'])->assertRedirect(route('login'));

    expect(DummyProduct::count())->toBe(0);
});

test('authenticated users can visit the dummy product admin pages', function (string $routeName, string $component) {
    $this->actingAs(User::factory()->create())
        ->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    ['dummy-products', 'DummyProducts'],
    ['dummy-product-brands', 'DummyProductBrands'],
    ['dummy-product-categories', 'DummyProductCategories'],
]);

test('dummy products are listed with brand and categories and can be filtered', function () {
    $this->actingAs(User::factory()->create());
    $brand = DummyProductBrand::factory()->create();
    $category = DummyProductCategory::factory()->create();
    $kaos = DummyProduct::factory()->for($brand, 'brand')->create(['title' => 'Kaos Polos', 'sku' => 'KAOS-1', 'created_at' => now()->subDay()]);
    $kaos->categories()->attach($category);
    DummyProduct::factory()->create(['title' => 'Topi', 'sku' => 'TOPI-1']);
    DummyProduct::factory()->create(['title' => 'Tanpa Brand', 'sku' => 'NB-1', 'dummy_product_brand_id' => null]);

    $this->getJson('/ajax/dummy-products')
        ->assertOk()
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('data.2.title', 'Kaos Polos')
        ->assertJsonPath('data.2.brand.id', $brand->id)
        ->assertJsonPath('data.2.categories.0.id', $category->id);

    $titles = fn (string $query): array => collect($this->getJson("/ajax/dummy-products?{$query}")->assertOk()->json('data'))
        ->pluck('title')->sort()->values()->all();

    expect($titles('search=kaos'))->toBe(['Kaos Polos'])
        ->and($titles('search=TOPI-1'))->toBe(['Topi'])
        ->and($titles("brand={$brand->id}"))->toBe(['Kaos Polos'])
        ->and($titles('brand=none'))->toBe(['Tanpa Brand'])
        ->and($titles("category={$category->id}"))->toBe(['Kaos Polos']);
});

test('users can create update and delete dummy products with categories', function () {
    $this->actingAs(User::factory()->create());
    $brand = DummyProductBrand::factory()->create();
    $categories = DummyProductCategory::factory()->count(2)->create();

    $this->postJson('/ajax/dummy-products', [
        'title' => 'Kemeja Flanel',
        'description' => 'Kemeja katun',
        'price' => 250000,
        'price_discount' => 199000.5,
        'rating' => 4.7,
        'stock' => 20,
        'weight' => 350,
        'sku' => 'KF-001',
        'dummy_product_brand_id' => $brand->id,
        'category_ids' => $categories->pluck('id')->all(),
    ])
        ->assertCreated()
        ->assertJsonPath('data.price', '250000.00')
        ->assertJsonPath('data.price_discount', '199000.50')
        ->assertJsonPath('data.image', null)
        ->assertJsonPath('data.brand.id', $brand->id)
        ->assertJsonCount(2, 'data.categories');

    $product = DummyProduct::where('sku', 'KF-001')->firstOrFail();

    $this->patchJson("/ajax/dummy-products/{$product->id}", [
        'title' => 'Kemeja Flanel Merah',
        'price' => 250000,
        'price_discount' => null,
        'rating' => 5,
        'stock' => 0,
        'sku' => 'KF-001',
        'dummy_product_brand_id' => null,
        'category_ids' => [$categories->first()->id],
    ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Kemeja Flanel Merah')
        ->assertJsonPath('data.price_discount', null)
        ->assertJsonPath('data.brand', null)
        ->assertJsonCount(1, 'data.categories');

    $this->deleteJson("/ajax/dummy-products/{$product->id}")->assertNoContent();

    $this->assertModelMissing($product);
    expect(DummyProductCategory::count())->toBe(2);
});

test('dummy product input is validated with json errors', function () {
    $this->actingAs(User::factory()->create());
    DummyProduct::factory()->create(['sku' => 'ADA-1']);

    $this->post('/ajax/dummy-products', [
        'title' => '',
        'price' => 1000,
        'price_discount' => 2000,
        'rating' => 6,
        'stock' => -1,
        'sku' => 'ADA-1',
        'dummy_product_brand_id' => 999,
        'category_ids' => [999],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'title', 'price_discount', 'rating', 'stock', 'sku', 'dummy_product_brand_id', 'category_ids.0',
        ]);

    expect(DummyProduct::count())->toBe(1);
});

test('users can manage dummy product brands and categories', function (string $endpoint, string $model) {
    $this->actingAs(User::factory()->create());

    $this->postJson($endpoint, ['name' => 'Nama Baru', 'slug' => 'nama-baru', 'description' => 'Deskripsi'])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'nama-baru')
        ->assertJsonPath('data.products_count', 0);

    $term = $model::where('slug', 'nama-baru')->firstOrFail();

    $this->post($endpoint, ['name' => 'Kembar', 'slug' => 'nama-baru'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['slug']);

    $this->patchJson("{$endpoint}/{$term->id}", ['name' => 'Nama Diubah', 'slug' => 'nama-baru'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Nama Diubah');

    $this->getJson("{$endpoint}?all=1&search=diubah")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonMissingPath('meta');

    $this->deleteJson("{$endpoint}/{$term->id}")->assertNoContent();
    $this->assertModelMissing($term);
})->with([
    'brands' => ['/ajax/dummy-product-brands', DummyProductBrand::class],
    'categories' => ['/ajax/dummy-product-categories', DummyProductCategory::class],
]);

test('brand and category product counts are included in listings', function () {
    $this->actingAs(User::factory()->create());
    $brand = DummyProductBrand::factory()->create();
    $category = DummyProductCategory::factory()->create();
    DummyProduct::factory()->count(2)->for($brand, 'brand')->create()
        ->each(fn (DummyProduct $product) => $product->categories()->attach($category));

    $this->getJson('/ajax/dummy-product-brands')->assertJsonPath('data.0.products_count', 2);
    $this->getJson('/ajax/dummy-product-categories')->assertJsonPath('data.0.products_count', 2);
});

test('product pictures are uploaded, replaced, removed and deleted with the product', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->create());
    $category = DummyProductCategory::factory()->create();

    $response = $this->post('/ajax/dummy-products', [
        'title' => 'Sepatu Lari',
        'price' => 500000,
        'rating' => 4,
        'stock' => 3,
        'sku' => 'SL-001',
        'category_ids' => [$category->id],
        'image_file' => UploadedFile::fake()->image('sepatu.jpg', 800, 800),
    ])->assertCreated();

    $firstImage = $response->json('data.image');

    expect($firstImage)->toStartWith('dummy-products/'.now()->format('Y/m').'/')
        ->and($response->json('data.image_url'))->toBe(Storage::disk('public')->url($firstImage));
    Storage::disk('public')->assertExists($firstImage);

    $product = DummyProduct::where('sku', 'SL-001')->firstOrFail();
    $productFields = ['title' => 'Sepatu Lari', 'price' => 500000, 'rating' => 4, 'stock' => 3, 'sku' => 'SL-001'];

    $secondImage = $this->post("/ajax/dummy-products/{$product->id}", [
        '_method' => 'PATCH',
        ...$productFields,
        'category_ids' => '',
        'image_file' => UploadedFile::fake()->image('sepatu-baru.png'),
    ])
        ->assertOk()
        ->assertJsonCount(0, 'data.categories')
        ->json('data.image');

    Storage::disk('public')->assertMissing($firstImage);
    Storage::disk('public')->assertExists($secondImage);

    $this->post("/ajax/dummy-products/{$product->id}", ['_method' => 'PATCH', ...$productFields])
        ->assertOk()
        ->assertJsonPath('data.image', $secondImage);

    $this->post("/ajax/dummy-products/{$product->id}", ['_method' => 'PATCH', ...$productFields, 'remove_image' => '1'])
        ->assertOk()
        ->assertJsonPath('data.image', null)
        ->assertJsonPath('data.image_url', null);

    Storage::disk('public')->assertMissing($secondImage);

    $thirdImage = $this->post("/ajax/dummy-products/{$product->id}", [
        '_method' => 'PATCH',
        ...$productFields,
        'image_file' => UploadedFile::fake()->image('lagi.webp'),
    ])->json('data.image');

    $this->deleteJson("/ajax/dummy-products/{$product->id}")->assertNoContent();

    Storage::disk('public')->assertMissing($thirdImage);
});

test('external image urls are shown as is and never deleted from storage', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->create());
    $product = DummyProduct::factory()->create(['image' => 'https://picsum.photos/seed/x/600/600']);

    $this->getJson('/ajax/dummy-products')
        ->assertJsonPath('data.0.image_url', 'https://picsum.photos/seed/x/600/600');

    $this->deleteJson("/ajax/dummy-products/{$product->id}")->assertNoContent();
});

test('product picture uploads must be small images', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->create());
    $fields = ['title' => 'Gambar Salah', 'price' => 1000, 'rating' => 0, 'stock' => 0, 'sku' => 'GS-001'];

    foreach ([
        UploadedFile::fake()->create('skrip.php', 1, 'application/x-php'),
        UploadedFile::fake()->create('logo.svg', 1, 'image/svg+xml'),
        UploadedFile::fake()->image('besar.jpg')->size(5 * 1024 + 1),
    ] as $file) {
        $this->post('/ajax/dummy-products', [...$fields, 'image_file' => $file])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image_file']);
    }

    expect(DummyProduct::count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});
