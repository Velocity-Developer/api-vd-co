<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequestLogResource extends JsonResource
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
            'route' => $this->route,
            'method' => $this->method,
            'request' => $this->request,
            'status' => $this->status,
            'website_id' => $this->website_id,
            'license_id' => $this->license_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'website' => $this->whenLoaded('website'),
            'license' => $this->whenLoaded('license'),
        ];
    }
}
