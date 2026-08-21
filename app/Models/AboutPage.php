<?php

namespace App\Models;

use App\Models\Concerns\HasCoverImage;
use Illuminate\Database\Eloquent\Model;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\HasTranslations;

/**
 * Single-row model holding the whole About page. Carries two images rather than
 * one, so it overrides the shared single-image collection.
 */
class AboutPage extends Model implements HasMedia
{
    use HasCoverImage;
    use HasTranslations;

    protected $guarded = [];

    public array $translatable = [
        'hero_title',
        'hero_intro',
        'journey_title',
        'philosophy_quote',
        'philosophy_note',
        'gear_title',
    ];

    protected function casts(): array
    {
        return [
            'disciplines' => 'array',
            'journey_paragraphs' => 'array',
            'timeline' => 'array',
            'approach' => 'array',
            'gear' => 'array',
        ];
    }

    public function registerMediaCollections(): void
    {
        foreach (['hero_image', 'gear_image'] as $collection) {
            $this->addMediaCollection($collection)
                ->singleFile()
                ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif']);
        }
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('preview')
            ->fit(Fit::Max, 1400, 1400)
            ->format('webp')
            ->nonQueued();
    }

    /** @return array<string, string>|null */
    public function urlsFor(string $collection): ?array
    {
        $media = $this->getFirstMedia($collection);

        if (! $media) {
            return null;
        }

        return [
            'preview' => $media->getFullUrl('preview'),
            'original' => $media->getFullUrl(),
        ];
    }

    public static function current(): self
    {
        return static::firstOrCreate([]);
    }
}
