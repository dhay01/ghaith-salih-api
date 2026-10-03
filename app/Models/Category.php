<?php

namespace App\Models;

use App\Models\Concerns\HasCoverImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Translatable\HasTranslations;

/**
 * Gallery filters and blog categories, discriminated by `type`. The gallery's
 * filter bar and the home page's category showcase both read from here, so adding
 * a category in the dashboard adds it to both without a deploy.
 */
class Category extends Model implements HasMedia
{
    use HasCoverImage;
    use HasTranslations;

    public const TYPE_WORK = 'work';

    public const TYPE_POST = 'post';

    protected $guarded = [];

    public array $translatable = ['name'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'grid_span' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    /**
     * What a category's tile shows: its own cover when one is set, otherwise its
     * newest published photograph that has an image.
     *
     * A category with photographs in it but no cover of its own rendered as an
     * empty labelled tile, which reads as broken rather than as unset — the
     * photographs are right there. Its own image still wins when one is set, so
     * choosing a cover is a decision, not a chore.
     */
    public function tileSource(): ?Model
    {
        if ($this->getFirstMedia($this->coverCollection())) {
            return $this;
        }

        return $this->photos()
            ->where('is_published', true)
            ->latest()
            ->latest('id')
            ->get()
            ->first(fn (Photo $photo) => $photo->imageUrls() !== null);
    }

    public function tileImageUrls(): ?array
    {
        return $this->tileSource()?->imageUrls();
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type)->orderBy('position');
    }
}
