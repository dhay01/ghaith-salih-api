<?php

namespace App\Models\Concerns;

use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * One image per record, with the three sizes the frontend actually asks for.
 *
 * Conversions are non-queued: this is a low-volume dashboard and a photographer
 * uploading a cover expects to see it immediately, not after a worker runs.
 * Gigapixel originals are the exception and are handled by their own tiling job.
 */
trait HasCoverImage
{
    // Composed here rather than in each model so the two registration methods below
    // cleanly take precedence over the package's empty defaults.
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection($this->coverCollection())
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Max, 600, 600)
            ->format('webp')
            ->nonQueued();

        $this->addMediaConversion('preview')
            ->fit(Fit::Max, 1400, 1400)
            ->format('webp')
            ->nonQueued();

        $this->addMediaConversion('full')
            ->fit(Fit::Max, 2600, 2600)
            ->format('webp')
            ->nonQueued();
    }

    public function coverCollection(): string
    {
        return 'image';
    }

    /**
     * Absolute URLs for every size, or null when no file has been uploaded yet —
     * the frontend's ImageSlot degrades to a labelled placeholder on null.
     *
     * @return array<string, string>|null
     */
    public function imageUrls(): ?array
    {
        $media = $this->getFirstMedia($this->coverCollection());

        if (! $media) {
            return null;
        }

        return [
            'thumb' => $media->getFullUrl('thumb'),
            'preview' => $media->getFullUrl('preview'),
            'full' => $media->getFullUrl('full'),
            'original' => $media->getFullUrl(),
        ];
    }
}
