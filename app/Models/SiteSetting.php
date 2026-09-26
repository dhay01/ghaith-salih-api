<?php

namespace App\Models;

use App\Models\Concerns\HasCoverImage;
use Illuminate\Database\Eloquent\Model;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\HasTranslations;

/**
 * Single-row model. `current()` is the only supported way to read it — it creates
 * the row on first use so a fresh install never has to remember to seed it.
 */
class SiteSetting extends Model implements HasMedia
{
    use HasCoverImage {
        registerMediaCollections as protected registerCoverCollections;
        registerMediaConversions as protected registerCoverConversions;
    }
    use HasTranslations;

    protected $guarded = [];

    public array $translatable = ['tagline', 'studio', 'author_location', 'author_bio'];

    protected function casts(): array
    {
        return [
            'socials' => 'array',
        ];
    }

    public function coverCollection(): string
    {
        return 'author_photo';
    }

    /**
     * The logo is its own collection, and only ever holds one file.
     *
     * Without singleFile() a second upload is added rather than replacing the
     * first, and getFirstMedia() keeps returning the original — so replacing a
     * logo appears to do nothing, and a broken one cannot be replaced at all.
     */
    public function registerMediaCollections(): void
    {
        $this->registerCoverCollections();

        $this->addMediaCollection('logo')
            ->singleFile()
            ->useDisk(config('media-library.disk_name', 'public'))
            ->acceptsMimeTypes(['image/png']);
    }

    /**
     * A logo is not a photograph, so it does not want the photograph sizes.
     *
     * Left to the inherited conversions it would be re-encoded at 600, 1400 and
     * 2600px, none of which it is ever displayed at. One conversion bounded by
     * height covers both the header and the footer at three times their largest
     * rendered size, and webp is used so the transparency survives.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        if ($media?->collection_name === 'logo') {
            $this->addMediaConversion('logo')
                ->fit(Fit::Max, 1200, 260)
                ->format('webp')
                ->nonQueued();

            return;
        }

        $this->registerCoverConversions($media);
    }

    /**
     * The logo, at a size worth sending.
     *
     * Falls back to the uploaded file if the conversion has not been generated —
     * better a heavy logo than none, and it keeps working if GD ever refuses the
     * file.
     */
    public function logoUrl(): ?string
    {
        $media = $this->getFirstMedia('logo');

        if (! $media) {
            return null;
        }

        if ($media->hasGeneratedConversion('logo')) {
            return $media->getFullUrl('logo');
        }

        // Tiny PNG only. A huge upload must not land in the header.
        return $media->size <= 512 * 1024 ? $media->getFullUrl() : null;
    }

    public static function current(): self
    {
        return static::firstOrCreate([], ['name' => 'ghaith salih']);
    }
}
