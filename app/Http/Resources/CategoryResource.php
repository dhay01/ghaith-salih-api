<?php

namespace App\Http\Resources;

use App\Models\Photo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Category */
class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $tile = $this->tileSource();

        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'position' => $this->position,
            'grid_span' => $this->grid_span,
            'grid_ratio' => $this->grid_ratio,
            'show_on_home' => $this->show_on_home,
            // Only present when the caller asked for counts.
            'photos_count' => $this->whenCounted('photos'),
            'images' => $tile?->imageUrls(),
            'ratio' => $tile?->imageRatio() ?? ($tile instanceof Photo ? $tile->ratio : null),
        ];
    }
}
