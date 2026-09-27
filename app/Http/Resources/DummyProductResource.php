<?php

namespace App\Http\Resources;

use App\Models\DummyProduct;
use App\Models\DummyProductBrand;
use App\Models\DummyProductCategory;
use App\Models\DummySeller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DummyProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'price' => $this->price,
            'price_discount' => $this->price_discount,
            'rating' => $this->rating,
            'stock' => $this->stock,
            'weight' => $this->weight,
            'sku' => $this->sku,
            'image' => $this->image,
            'image_url' => DummyProduct::publicImageUrl($this->image),
            'gallery' => $this->whenLoaded('images', fn () => $this->images->map(fn ($image) => [
                'id' => $image->id,
                'path' => $image->path,
                'url' => DummyProduct::publicImageUrl($image->path),
                'sort_order' => $image->sort_order,
            ])),
            'dummy_product_brand_id' => $this->dummy_product_brand_id,
            'brand' => $this->whenLoaded('brand', fn () => $this->brand ? [
                ...$this->brand->only(['id', 'name', 'slug']),
                'image_url' => DummyProductBrand::publicImageUrl($this->brand->image),
            ] : null),
            'dummy_seller_id' => $this->dummy_seller_id,
            'seller' => $this->whenLoaded('seller', fn () => $this->seller ? [
                ...$this->seller->only(['id', 'name', 'slug', 'city', 'is_verified']),
                'image_url' => DummySeller::publicImageUrl($this->seller->image),
            ] : null),
            'categories' => $this->whenLoaded('categories', fn () => $this->categories->map(fn (DummyProductCategory $category) => [
                ...$category->only(['id', 'name', 'slug']),
                'image_url' => DummyProductCategory::publicImageUrl($category->image),
            ])),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
