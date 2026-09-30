<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
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
     * The dashboard's Builder stores every block as {type, data: {...}}; the article
     * page reads them flat ({type, paragraphs}, {type, text}, ...). Keys a block
     * carries outside `data` are kept wherever `data` has nothing to say, so a body
     * written flat (the old seed shape) still renders.
     *
     * In-body figures are stored as a disk path so the dashboard can upload one
     * inline; the frontend only ever wants a URL.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function resolveBody(): array
    {
        return collect($this->body ?? [])
            ->map(function (array $block): array {
                $block = array_merge(
                    Arr::except($block, 'data'),
                    array_filter($block['data'] ?? [], fn ($value) => filled($value)),
                );

                if (($block['type'] ?? null) === 'figure' && filled($block['path'] ?? null)) {
                    $block['src'] = Storage::disk(config('media-library.disk_name'))->url($block['path']);
                }

                return $block;
            })
            ->all();
    }
}
