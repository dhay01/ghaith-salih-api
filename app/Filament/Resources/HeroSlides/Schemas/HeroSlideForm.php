<?php

namespace App\Filament\Resources\HeroSlides\Schemas;

use App\Filament\Support\Translatable;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class HeroSlideForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            SpatieMediaLibraryFileUpload::make('image')
                ->collection('image')
                ->image()
                ->imageEditor()
                ->columnSpanFull(),

            Translatable::text('alt', 'Alt text'),

            TextInput::make('position')->numeric()->default(0),
            Toggle::make('is_published')->label('Published')->default(true),
        ]);
    }
}
