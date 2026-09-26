<?php

namespace App\Models;

use Database\Factories\DummyProductCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DummyProductCategory extends Model
{
    /** @use HasFactory<DummyProductCategoryFactory> */
    use HasFactory;

    protected $guarded = [];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(DummyProduct::class)->withTimestamps();
    }
}
