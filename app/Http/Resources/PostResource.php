<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Models\Post */
class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'standfirst' => $this->standfirst,
            'tags' => $this->tags ?? [],
            'read_minutes' => $this->read_minutes,
            'is_featured' => $this->is_featured,
            'published_on' => $this->published_on?->toDateString(),
            'category' => [
                'slug' => $this->category?->slug,
                'name' => $this->category?->name,
            ],
            'images' => $this->imageUrls(),
            'body' => $this->resolveBody(),
        ];
    }

    /**
     * In-body figures are stored as a disk path so the dashboard can upload one
     * inline; the frontend only ever wants a URL.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function resolveBody(): array
    {
        return collect($this->body ?? [])
            ->map(function (array $block): array {
                if (($block['type'] ?? null) === 'figure' && ! empty($block['path'])) {
                    $block['src'] = Storage::disk('public')->url($block['path']);
                }

                return $block;
            })
            ->all();
    }
}
