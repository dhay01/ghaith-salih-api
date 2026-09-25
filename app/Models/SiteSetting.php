<?php

namespace App\Models;

use App\Models\Concerns\HasCoverImage;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Translatable\HasTranslations;

/**
 * Single-row model. `current()` is the only supported way to read it — it creates
 * the row on first use so a fresh install never has to remember to seed it.
 */
class SiteSetting extends Model implements HasMedia
{
    use HasCoverImage;
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
     * The wordmark shown in the site header, if one has been uploaded.
     *
     * Served as the file that was uploaded rather than through a conversion: a
     * logo is already small, and re-encoding it only costs it sharpness at the
     * exact size it is displayed.
     */
    public function logoUrl(): ?string
    {
        return $this->getFirstMedia('logo')?->getFullUrl();
    }

    public static function current(): self
    {
        return static::firstOrCreate([], ['name' => 'ghaith salih']);
    }
}
