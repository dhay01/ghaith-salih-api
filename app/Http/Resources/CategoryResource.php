<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Category */
class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'position' => $this->position,
            'grid_span' => $this->grid_span,
            'grid_ratio' => $this->grid_ratio,
            // Only present when the caller asked for counts.
            'photos_count' => $this->whenCounted('photos'),
            'images' => $this->imageUrls(),
        ];
    }
}
