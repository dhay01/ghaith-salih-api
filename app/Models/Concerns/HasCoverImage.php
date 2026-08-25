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
            // Stated outright rather than left to the default. An upload that
            // lands on the `local` disk is stored under storage/app/private,
            // which the web server will not serve — the image simply 403s and
            // the gallery shows an empty tile.
            ->useDisk(config('media-library.disk_name', 'public'))
            // TIFF belongs here even though the site never serves it: stitched
            // panoramas and gigapixel originals are usually delivered as TIFF, and
            // vips reads it happily to build the tiles and web-sized versions.
            ->acceptsMimeTypes([
                'image/jpeg',
                'image/png',
                'image/webp',
                'image/avif',
                'image/tiff',
                'image/x-tiff',
            ]);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // GD decodes an entire image into memory to resize it, so a gigapixel
        // original would need many gigabytes and simply dies. Large uploads get
        // their web-sized versions from vips instead, which streams.
        if ($media && static::isOversizedUpload($media)) {
            return;
        }

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

    public static function isOversizedUpload(Media $media): bool
    {
        return $media->size > (int) config('gigapixel.large_file_bytes');
    }

    /**
     * Overridden by models that can generate their own derivatives with vips.
     *
     * @return array<string, string>|null
     */
    protected function generatedDerivativeUrls(): ?array
    {
        return null;
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

        // A large upload has no GD conversions; whoever generated its vips
        // derivatives supplies them instead. Falling back to the original keeps
        // the image visible either way, just heavier.
        if (static::isOversizedUpload($media)) {
            $generated = $this->generatedDerivativeUrls();

            return $generated ?? array_fill_keys(
                ['thumb', 'preview', 'full', 'original'],
                $media->getFullUrl(),
            );
        }

        return [
            'thumb' => $media->getFullUrl('thumb'),
            'preview' => $media->getFullUrl('preview'),
            'full' => $media->getFullUrl('full'),
            'original' => $media->getFullUrl(),
        ];
    }
}
