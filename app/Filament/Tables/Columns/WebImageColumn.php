<?php

namespace App\Filament\Tables\Columns;

use Filament\Tables\Columns\ImageColumn;

class WebImageColumn
{
    public static function thumb(string $name = 'image'): ImageColumn
    {
        return ImageColumn::make($name)
            ->label('')
            ->getStateUsing(function ($record) {
                $urls = $record->imageUrls();

                return $urls['thumb'] ?? $urls['preview'] ?? null;
            })
            ->height(64)
            ->checkFileExistence(false);
    }
}
