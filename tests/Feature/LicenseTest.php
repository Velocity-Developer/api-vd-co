<?php

use App\Models\License;
use App\Models\User;
use Database\Seeders\LicenseSeeder;
use Illuminate\Support\Facades\Schema;

test('licenses table has the expected columns', function () {
    expect(Schema::hasColumns('licenses', [
        'id',
        'user_id',
        'code',
        'is_active',
        'used_at',
        'expires_at',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('a license can belong to a user', function () {
    $user = User::factory()->create();
    $license = License::factory()->for($user)->create();

    expect($license->user)
        ->toBeInstanceOf(User::class)
        ->id->toBe($user->id);
});

test('license attributes are cast correctly', function () {
    $license = License::factory()->create([
        'is_active' => false,
        'used_at' => now(),
        'expires_at' => now()->addYear(),
    ]);

    expect($license->is_active)->toBeFalse()
        ->and($license->used_at)->toBeInstanceOf(DateTimeInterface::class)
        ->and($license->expires_at)->toBeInstanceOf(DateTimeInterface::class);
});

test('license seeder creates license codes once', function () {
    $this->seed(LicenseSeeder::class);
    $this->seed(LicenseSeeder::class);

    expect(License::count())->toBe(10)
        ->and(License::where('code', 'APICO-2026-0001')->exists())->toBeTrue()
        ->and(License::where('is_active', true)->count())->toBe(10);
});
