<?php

namespace App\Http\Resources;

use App\Models\DummySeller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DummySellerResource extends JsonResource
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
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image' => $this->image,
            'image_url' => DummySeller::publicImageUrl($this->image),
            'banner' => $this->banner,
            'banner_url' => DummySeller::publicImageUrl($this->banner),
            'email' => $this->email,
            'phone' => $this->phone,
            'city' => $this->city,
            'address' => $this->address,
            'rating' => $this->rating,
            'is_verified' => $this->is_verified,
            'products_count' => $this->whenCounted('products'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
