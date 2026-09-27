<?php

namespace App\Models;

use App\Models\Concerns\HasPublicImage;
use Database\Factories\DummyProductCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DummyProductCategory extends Model
{
    /** @use HasFactory<DummyProductCategoryFactory> */
    use HasFactory, HasPublicImage;

    protected $guarded = [];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(DummyProduct::class)->withTimestamps();
    }

    protected static function imageDirectory(): string
    {
        return 'dummy-product-categories';
    }
}
