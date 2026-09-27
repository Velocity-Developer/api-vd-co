<?php

namespace App\Models;

use App\Models\Concerns\HasPublicImage;
use Database\Factories\DummySellerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DummySeller extends Model
{
    /** @use HasFactory<DummySellerFactory> */
    use HasFactory, HasPublicImage;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rating' => 'decimal:2',
            'is_verified' => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(DummyProduct::class);
    }

    protected static function imageDirectory(): string
    {
        return 'dummy-sellers';
    }

    protected static function imageColumns(): array
    {
        return ['image', 'banner'];
    }
}
