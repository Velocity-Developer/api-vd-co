<?php

namespace App\Models;

use Database\Factories\DummyProductImageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DummyProductImage extends Model
{
    /** @use HasFactory<DummyProductImageFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(DummyProduct::class, 'dummy_product_id');
    }
}
