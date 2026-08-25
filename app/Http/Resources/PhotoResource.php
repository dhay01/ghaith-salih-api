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
            // What the visitor can actually do, not what the dashboard asked for:
            // a photo flagged for deep zoom whose tiles are still being built is
            // not yet zoomable, and offering the control would dead-end.
            'is_zoomable' => $this->hasDeepZoom(),
            'deep_zoom' => $this->deepZoomSource(),
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
