<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Page */
class PageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'eyebrow' => $this->eyebrow,
            'title' => $this->title,
            'intro' => $this->intro,
            // Keyed by section name so a template can pull `sections.about`
            // without caring about ordering. Cast to an object so a page with no
            // sections serialises as {} rather than [] — an empty PHP array would
            // otherwise change the field's JSON type.
            'sections' => (object) collect($this->sections ?? [])
                ->keyBy(fn (array $section) => $section['key'] ?? '')
                ->all(),
        ];
    }
}
