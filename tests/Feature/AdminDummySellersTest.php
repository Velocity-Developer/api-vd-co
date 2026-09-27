<?php

use App\Models\DummyProduct;
use App\Models\DummySeller;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function sellerFields(array $overrides = []): array
{
    return [
        'name' => 'Toko Sinar',
        'slug' => 'toko-sinar',
        'description' => 'Toko pakaian',
        'email' => 'halo@tokosinar.test',
        'phone' => '0812-3456-7890',
        'city' => 'Bandung',
        'address' => 'Jl. Merdeka 1',
        'rating' => 4.8,
        'is_verified' => '1',
        ...$overrides,
    ];
}

test('dummy seller table and product link have the expected columns', function () {
    expect(Schema::hasColumns('dummy_sellers', [
        'id', 'name', 'slug', 'description', 'image', 'banner', 'email', 'phone', 'city', 'address', 'rating', 'is_verified',
    ]))->toBeTrue()
        ->and(Schema::hasColumn('dummy_products', 'dummy_seller_id'))->toBeTrue();
});

test('guests cannot manage dummy sellers', function () {
    $this->get(route('dummy-sellers'))->assertRedirect(route('login'));
    $this->post('/ajax/dummy-sellers', sellerFields())->assertRedirect(route('login'));

    expect(DummySeller::count())->toBe(0);
});

test('authenticated users can visit the dummy sellers page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dummy-sellers'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('DummySellers'));
});

test('sellers are listed by name with product counts and can be searched or filtered', function () {
    $this->actingAs(User::factory()->create());
    $zeta = DummySeller::factory()->create(['name' => 'Zeta Store', 'slug' => 'zeta', 'city' => 'Medan', 'is_verified' => true]);
    DummySeller::factory()->create(['name' => 'Alfa Store', 'slug' => 'alfa', 'city' => 'Jakarta', 'is_verified' => false]);
    DummyProduct::factory()->count(2)->create(['dummy_seller_id' => $zeta->id]);

    $this->getJson('/ajax/dummy-sellers')
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'alfa')
        ->assertJsonPath('data.1.slug', 'zeta')
        ->assertJsonPath('data.1.products_count', 2)
        ->assertJsonPath('data.1.is_verified', true);

    $this->getJson('/ajax/dummy-sellers?search=medan')->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'zeta');
    $this->getJson('/ajax/dummy-sellers?verified=0')->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'alfa');
    $this->getJson('/ajax/dummy-sellers?all=1')->assertJsonCount(2, 'data')->assertJsonMissingPath('meta');
});

test('users can create update and delete sellers with a logo', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->create());

    $response = $this->post('/ajax/dummy-sellers', [
        ...sellerFields(),
        'image_file' => UploadedFile::fake()->image('logo.png', 300, 300),
    ])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'toko-sinar')
        ->assertJsonPath('data.rating', '4.80')
        ->assertJsonPath('data.is_verified', true)
        ->assertJsonPath('data.city', 'Bandung');

    $logo = $response->json('data.image');
    expect($logo)->toStartWith('dummy-sellers/'.now()->format('Y/m').'/');
    Storage::disk('public')->assertExists($logo);

    $seller = DummySeller::where('slug', 'toko-sinar')->firstOrFail();
    $product = DummyProduct::factory()->create(['dummy_seller_id' => $seller->id]);

    $this->post("/ajax/dummy-sellers/{$seller->id}", [
        '_method' => 'PATCH',
        ...sellerFields(['name' => 'Toko Sinar Jaya', 'is_verified' => '0', 'email' => '', 'phone' => '']),
        'remove_image' => '1',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Toko Sinar Jaya')
        ->assertJsonPath('data.is_verified', false)
        ->assertJsonPath('data.email', null)
        ->assertJsonPath('data.image_url', null)
        ->assertJsonPath('data.products_count', 1);

    Storage::disk('public')->assertMissing($logo);

    $this->deleteJson("/ajax/dummy-sellers/{$seller->id}")->assertNoContent();

    $this->assertModelMissing($seller);
    expect($product->fresh()->dummy_seller_id)->toBeNull();
});

test('seller input is validated with json errors', function () {
    $this->actingAs(User::factory()->create());
    DummySeller::factory()->create(['slug' => 'toko-sinar']);

    $this->post('/ajax/dummy-sellers', sellerFields([
        'name' => '',
        'email' => 'bukan-email',
        'phone' => 'telepon saya',
        'rating' => 6,
        'is_verified' => 'mungkin',
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'slug', 'email', 'phone', 'rating', 'is_verified']);
});

test('products can be assigned to a seller and filtered by seller', function () {
    $this->actingAs(User::factory()->create());
    $seller = DummySeller::factory()->create(['city' => 'Surabaya']);
    $product = DummyProduct::factory()->create(['title' => 'Punya Seller', 'dummy_seller_id' => null]);
    DummyProduct::factory()->create(['title' => 'Tanpa Seller', 'dummy_seller_id' => null]);

    $this->patchJson("/ajax/dummy-products/{$product->id}", [
        'title' => 'Punya Seller',
        'price' => 1000,
        'rating' => 0,
        'stock' => 1,
        'sku' => $product->sku,
        'dummy_seller_id' => $seller->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.dummy_seller_id', $seller->id)
        ->assertJsonPath('data.seller.slug', $seller->slug)
        ->assertJsonPath('data.seller.city', 'Surabaya');

    $titles = fn (string $query): array => collect($this->getJson("/ajax/dummy-products?{$query}")->json('data'))->pluck('title')->all();

    expect($titles("seller={$seller->id}"))->toBe(['Punya Seller'])
        ->and($titles('seller=none'))->toBe(['Tanpa Seller']);

    $this->patchJson("/ajax/dummy-products/{$product->id}", [
        'title' => 'Punya Seller', 'price' => 1000, 'rating' => 0, 'stock' => 1, 'sku' => $product->sku, 'dummy_seller_id' => 999,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['dummy_seller_id']);
});

test('seller banners are uploaded, replaced, removed and deleted with the seller alongside the logo', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->create());

    $response = $this->post('/ajax/dummy-sellers', [
        ...sellerFields(),
        'image_file' => UploadedFile::fake()->image('logo.png', 300, 300),
        'banner_file' => UploadedFile::fake()->image('banner.jpg', 1200, 400),
    ])->assertCreated();

    $logo = $response->json('data.image');
    $banner = $response->json('data.banner');

    expect($banner)->toStartWith('dummy-sellers/banners/'.now()->format('Y/m').'/')
        ->and($response->json('data.banner_url'))->toBe(Storage::disk('public')->url($banner));
    Storage::disk('public')->assertExists([$logo, $banner]);

    $seller = DummySeller::where('slug', 'toko-sinar')->firstOrFail();
    $fields = ['_method' => 'PATCH', ...sellerFields()];

    $newBanner = $this->post("/ajax/dummy-sellers/{$seller->id}", [...$fields, 'banner_file' => UploadedFile::fake()->image('baru.webp', 1200, 400)])
        ->assertOk()
        ->assertJsonPath('data.image', $logo)
        ->json('data.banner');

    Storage::disk('public')->assertMissing($banner);
    Storage::disk('public')->assertExists([$logo, $newBanner]);

    $this->post("/ajax/dummy-sellers/{$seller->id}", [...$fields, 'remove_banner' => '1'])
        ->assertOk()
        ->assertJsonPath('data.banner', null)
        ->assertJsonPath('data.banner_url', null)
        ->assertJsonPath('data.image', $logo);

    Storage::disk('public')->assertMissing($newBanner);

    $lastBanner = $this->post("/ajax/dummy-sellers/{$seller->id}", [...$fields, 'banner_file' => UploadedFile::fake()->image('lagi.jpg')])
        ->json('data.banner');

    $this->deleteJson("/ajax/dummy-sellers/{$seller->id}")->assertNoContent();

    Storage::disk('public')->assertMissing([$logo, $lastBanner]);

    $this->post('/ajax/dummy-sellers', [...sellerFields(['slug' => 'svg']), 'banner_file' => UploadedFile::fake()->create('b.svg', 1, 'image/svg+xml')])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['banner_file']);
});
