<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AboutPage */
class AboutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'hero_title' => $this->hero_title,
            'hero_intro' => $this->hero_intro,
            'disciplines' => $this->disciplines ?? [],

            'journey_title' => $this->journey_title,
            'journey_paragraphs' => $this->journey_paragraphs ?? [],
            'timeline' => $this->timeline ?? [],

            'philosophy_quote' => $this->philosophy_quote,
            'philosophy_note' => $this->philosophy_note,

            'approach' => $this->approach ?? [],

            'gear_title' => $this->gear_title,
            'gear' => $this->gear ?? [],

            'hero_image' => $this->urlsFor('hero_image'),
            'gear_image' => $this->urlsFor('gear_image'),
        ];
    }
}
