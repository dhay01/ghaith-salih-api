<?php

namespace App\Filament\Forms\Components;

use App\Models\AboutPage;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * FilePond preview uses a web-sized conversion, never the original. Spatie's
 * default falls back to the uploaded file when a conversion is missing, which
 * put a 378 MB panorama in the dashboard.
 */
class CoverImageUpload extends SpatieMediaLibraryFileUpload
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->image();
        $this->conversion('thumb');
        $this->maxSize((int) (config('gigapixel.large_file_bytes') / 1024));

        $this->getUploadedFileUsing(function (CoverImageUpload $component, string $file): ?array {
            $record = $component->getRecord();
            /** @var ?Media $media */
            $media = $record?->getRelationValue('media')->firstWhere('uuid', $file);

            if (! $media) {
                return null;
            }

            $conversion = $component->getConversion();
            $url = null;

            if ($conversion && $media->hasGeneratedConversion($conversion)) {
                $url = $media->getUrl($conversion);
            } elseif (self::isOversized($record, $media)) {
                $url = self::oversizedPreview($record, $media, $conversion);
            } else {
                $url = $media->getUrl();
            }

            return [
                'name' => $media->getAttributeValue('name') ?? $media->getAttributeValue('file_name'),
                'size' => $media->getAttributeValue('size'),
                'type' => $media->getAttributeValue('mime_type'),
                'url' => $url ? Str::sanitizeUrl($url) : null,
            ];
        });
    }

    protected static function isOversized(mixed $record, Media $media): bool
    {
        return is_object($record)
            && method_exists($record, 'isOversizedUpload')
            && $record::isOversizedUpload($media);
    }

    protected static function oversizedPreview(mixed $record, Media $media, ?string $conversion): ?string
    {
        $urls = $record instanceof AboutPage
            ? $record->urlsFor($media->collection_name)
            : (method_exists($record, 'imageUrls') ? $record->imageUrls() : null);

        if (! $urls) {
            return null;
        }

        return $urls[$conversion ?: 'thumb'] ?? $urls['thumb'] ?? $urls['preview'] ?? null;
    }
}
