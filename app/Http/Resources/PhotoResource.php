<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Photo */
class PhotoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'location' => $this->location,
            'gear' => $this->gear,
            'alt' => $this->alt ?: $this->title,
            'ratio' => $this->ratio,
            'is_zoomable' => $this->is_zoomable,
            'dzi_path' => $this->dzi_path,
            'category' => $this->whenLoaded('category', fn () => [
                'slug' => $this->category?->slug,
                'name' => $this->category?->name,
            ]),
            // Null when nothing has been uploaded yet; the frontend's ImageSlot
            // renders a labelled placeholder rather than a broken image.
            'images' => $this->imageUrls(),
        ];
    }
}
