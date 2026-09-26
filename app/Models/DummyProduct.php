<?php

namespace App\Models;

use Database\Factories\DummyProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DummyProduct extends Model
{
    /** @use HasFactory<DummyProductFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'price_discount' => 'decimal:2',
            'rating' => 'decimal:2',
            'stock' => 'integer',
            'weight' => 'decimal:2',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(DummyProductBrand::class, 'dummy_product_brand_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(DummyProductCategory::class)->withTimestamps();
    }

    /**
     * Whether the image points at a file on the public disk rather than an external URL.
     */
    public static function isStoredImage(?string $image): bool
    {
        return filled($image) && ! Str::startsWith($image, ['http://', 'https://', '//']);
    }

    /**
     * Store an uploaded picture and return its path on the public disk.
     */
    public static function storeImage(UploadedFile $file): string
    {
        return $file->store('dummy-products/'.now()->format('Y/m'), 'public');
    }

    /**
     * Delete a stored picture file, leaving external URLs alone.
     */
    public static function deleteStoredImage(?string $image): void
    {
        if (static::isStoredImage($image)) {
            Storage::disk('public')->delete($image);
        }
    }
}
