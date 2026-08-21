<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SiteSetting */
class SiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'tagline' => $this->tagline,
            'email' => $this->email,
            'phone' => $this->phone,
            'phone_href' => $this->phone_href,
            'studio' => $this->studio,
            'socials' => $this->socials ?? [],
            // The footer prints a copyright year; deriving it here keeps it from
            // going stale in a hardcoded string on 1 January.
            'year' => (int) now()->format('Y'),
        ];
    }
}
