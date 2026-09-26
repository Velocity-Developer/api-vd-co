<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class MediaResource extends JsonResource
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
            'collection' => $this->collection,
            'disk' => $this->disk,
            'path' => $this->path,
            'url' => Storage::disk($this->disk)->url($this->path),
            'original_name' => $this->original_name,
            'file_name' => $this->file_name,
            'extension' => $this->extension,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'title' => $this->title,
            'alt_text' => $this->alt_text,
            'caption' => $this->caption,
            'metadata' => $this->metadata,
            'mediable_type' => $this->mediable_type,
            'mediable_id' => $this->mediable_id,
            'creator' => $this->whenLoaded('creator', fn () => $this->creator?->only(['id', 'name'])),
            'categories' => $this->whenLoaded('categories', fn () => $this->categories->map->only(['id', 'name', 'slug'])),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map->only(['id', 'name', 'slug'])),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
