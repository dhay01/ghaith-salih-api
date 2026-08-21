<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Page extends Model
{
    use HasTranslations;

    protected $guarded = [];

    public array $translatable = ['eyebrow', 'title', 'intro'];

    protected function casts(): array
    {
        return [
            'sections' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'key';
    }
}
