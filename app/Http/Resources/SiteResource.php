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
            'author' => [
                'name' => $this->author_name,
                'location' => $this->author_location,
                'bio' => $this->author_bio,
                'follow' => $this->author_follow,
                'images' => $this->imageUrls(),
            ],
            // The footer prints a copyright year; deriving it here keeps it from
            // going stale in a hardcoded string on 1 January.
            'year' => (int) now()->format('Y'),
        ];
    }
}
