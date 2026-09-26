<?php

namespace App\Filament\Resources\HeroSlides\Schemas;

use App\Filament\Forms\Components\CoverImageUpload;
use App\Filament\Support\Translatable;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class HeroSlideForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            CoverImageUpload::make('image')
                ->collection('image')
                ->imageEditor()
                ->columnSpanFull(),

            Translatable::text('alt', 'Alt text'),

            TextInput::make('position')->numeric()->default(0),
            Toggle::make('is_published')->label('Published')->default(true),
        ]);
    }
}
