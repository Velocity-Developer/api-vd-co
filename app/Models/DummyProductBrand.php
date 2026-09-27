<?php

namespace App\Models;

use App\Models\Concerns\HasPublicImage;
use Database\Factories\DummyProductBrandFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DummyProductBrand extends Model
{
    /** @use HasFactory<DummyProductBrandFactory> */
    use HasFactory, HasPublicImage;

    protected $guarded = [];

    public function products(): HasMany
    {
        return $this->hasMany(DummyProduct::class);
    }

    protected static function imageDirectory(): string
    {
        return 'dummy-product-brands';
    }
}
