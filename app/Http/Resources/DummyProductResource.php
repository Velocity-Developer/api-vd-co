<?php

namespace App\Http\Resources;

use App\Models\DummyProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
            'image_url' => $this->imageUrl(),
            'gallery' => $this->whenLoaded('images', fn () => $this->images->map(fn ($image) => [
                'id' => $image->id,
                'path' => $image->path,
                'url' => $this->urlFor($image->path),
                'sort_order' => $image->sort_order,
            ])),
            'dummy_product_brand_id' => $this->dummy_product_brand_id,
            'brand' => $this->whenLoaded('brand', fn () => $this->brand?->only(['id', 'name', 'slug'])),
            'categories' => $this->whenLoaded('categories', fn () => $this->categories->map->only(['id', 'name', 'slug'])),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Full URLs are used as is; anything else is a path on the public disk.
     */
    private function imageUrl(): ?string
    {
        return blank($this->image) ? null : $this->urlFor($this->image);
    }

    private function urlFor(string $path): string
    {
        return DummyProduct::isStoredImage($path) ? Storage::disk('public')->url($path) : $path;
    }
}
