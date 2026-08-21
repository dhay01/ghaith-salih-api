<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * Single-row model. `current()` is the only supported way to read it — it creates
 * the row on first use so a fresh install never has to remember to seed it.
 */
class SiteSetting extends Model
{
    use HasTranslations;

    protected $guarded = [];

    public array $translatable = ['tagline', 'studio'];

    protected function casts(): array
    {
        return [
            'socials' => 'array',
        ];
    }

    public static function current(): self
    {
        return static::firstOrCreate([], ['name' => 'ghaith salih']);
    }
}
