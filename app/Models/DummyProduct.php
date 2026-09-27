<?php

namespace App\Models;

use App\Models\Concerns\HasPublicImage;
use Database\Factories\DummyProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;

class DummyProduct extends Model
{
    /** @use HasFactory<DummyProductFactory> */
    use HasFactory, HasPublicImage;

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

    public function images(): HasMany
    {
        return $this->hasMany(DummyProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    protected static function imageDirectory(): string
    {
        return 'dummy-products';
    }

    /**
     * Store an uploaded gallery picture and return its path on the public disk.
     */
    public static function storeGalleryImage(UploadedFile $file): string
    {
        return $file->store(static::imageDirectory().'/gallery/'.now()->format('Y/m'), 'public');
    }
}
