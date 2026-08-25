<?php

namespace App\Models;

use App\Jobs\GenerateDeepZoomTiles;
use App\Models\Concerns\HasCoverImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;

class Photo extends Model implements HasMedia
{
    use HasCoverImage;
    use HasSlug;
    use HasTranslations;

    public const TILING_QUEUED = 'queued';

    public const TILING_PROCESSING = 'processing';

    public const TILING_READY = 'ready';

    public const TILING_FAILED = 'failed';

    protected $guarded = [];

    public array $translatable = ['title', 'location', 'gear', 'alt'];

    protected function casts(): array
    {
        return [
            'is_zoomable' => 'boolean',
            'is_published' => 'boolean',
            'position' => 'integer',
            'dzi_generated_at' => 'datetime',
            'dzi_meta' => 'array',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(fn (self $p) => $p->getTranslation('title', 'en'))
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * Deep zoom is only actually available once the tiles exist — the flag alone
     * means "wanted", not "ready", because slicing happens in the background.
     */
    public function hasDeepZoom(): bool
    {
        return $this->is_zoomable
            && $this->dzi_status === self::TILING_READY
            && filled($this->dzi_path);
    }

    /**
     * Tiles are stale when they were built from a different upload than the one
     * currently attached, which is how replacing the image triggers a re-slice
     * while renaming the photo does not.
     */
    public function needsTiling(): bool
    {
        $media = $this->getFirstMedia('image');

        if (! $media) {
            return false;
        }

        // A large upload is processed even when deep zoom is off, because its
        // web-sized versions can only come from vips.
        if (! $this->is_zoomable && ! static::isOversizedUpload($media)) {
            return false;
        }

        return $this->dzi_status !== self::TILING_READY
            || $this->dzi_media_id !== $media->getKey();
    }

    /** Base path shared by this photo's tiles and vips-generated versions. */
    public function derivativeBase(): string
    {
        return trim((string) config('gigapixel.directory'), '/').'/'.$this->slug;
    }

    /**
     * URLs of the versions vips produced, used in place of the GD conversions
     * that were skipped for an oversized upload.
     *
     * @return array<string, string>|null
     */
    protected function generatedDerivativeUrls(): ?array
    {
        if ($this->dzi_status !== self::TILING_READY) {
            return null;
        }

        $disk = Storage::disk(config('gigapixel.disk'));
        $base = $this->derivativeBase();
        $urls = [];

        foreach (array_keys((array) config('gigapixel.derivatives')) as $name) {
            $path = $base.'-'.$name.'.webp';

            if (! $disk->exists($path)) {
                return null;
            }

            $urls[$name] = $disk->url($path);
        }

        $urls['original'] = $this->getFirstMedia('image')?->getFullUrl() ?? $urls['full'];

        return $urls;
    }

    /**
     * Dispatches tiling if it is actually needed, and marks the photo queued so the
     * dashboard can say so. Safe to call more than once for the same upload — the
     * status guard keeps a save and an upload from queuing two jobs.
     */
    public static function queueTilingFor(?self $photo): void
    {
        if (! $photo || ! $photo->needsTiling()) {
            return;
        }

        if (in_array($photo->dzi_status, [self::TILING_QUEUED, self::TILING_PROCESSING], true)) {
            return;
        }

        $photo->forceFill([
            'dzi_status' => self::TILING_QUEUED,
            'dzi_error' => null,
            'dzi_progress' => null,
        ])->saveQuietly();

        GenerateDeepZoomTiles::dispatch($photo);
    }

    public function markTilingFailed(string $reason): void
    {
        $this->forceFill([
            'dzi_status' => self::TILING_FAILED,
            'dzi_error' => $reason,
            'dzi_progress' => null,
        ])->save();
    }

    /**
     * Everything the viewer needs to render the pyramid, including the base URL
     * its tiles hang off. Returned as data so the browser never fetches the .dzi.
     *
     * @return array<string, mixed>|null
     */
    public function deepZoomSource(): ?array
    {
        if (! $this->hasDeepZoom() || blank($this->dzi_meta)) {
            return null;
        }

        $disk = Storage::disk(config('gigapixel.disk'));

        return [
            // OpenSeadragon appends "<level>/<col>_<row>.<format>" to this.
            'tiles_url' => $disk->url($this->derivativeBase().'_files').'/',
            'width' => $this->dzi_meta['width'] ?? null,
            'height' => $this->dzi_meta['height'] ?? null,
            'tile_size' => $this->dzi_meta['tile_size'] ?? null,
            'overlap' => $this->dzi_meta['overlap'] ?? null,
            'format' => $this->dzi_meta['format'] ?? 'jpg',
        ];
    }
}
