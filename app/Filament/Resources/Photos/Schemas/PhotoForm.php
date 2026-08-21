<?php

namespace App\Filament\Resources\Photos\Schemas;

use App\Filament\Support\Translatable;
use App\Models\Category;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

class PhotoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Image')->schema([
                SpatieMediaLibraryFileUpload::make('image')
                    ->collection('image')
                    ->image()
                    ->imageEditor()
                    ->helperText('Leave empty and the site shows a labelled placeholder instead of a broken image.')
                    ->columnSpanFull(),
            ]),

            Section::make('Caption')->schema([
                Translatable::text('title', 'Title'),
                Translatable::text('location', 'Location'),
                Translatable::text('gear', 'Gear'),
                Translatable::text('alt', 'Alt text'),
            ]),

            Section::make('Placement')->columns(2)->schema([
                Select::make('category_id')
                    ->label('Category')
                    ->relationship(
                        'category',
                        'slug',
                        fn ($query) => $query->where('type', Category::TYPE_WORK)->orderBy('position'),
                    )
                    ->searchable()
                    ->preload(),

                TextInput::make('ratio')
                    ->required()
                    ->default('3/2')
                    ->helperText('Aspect ratio, e.g. 16/10. Reserves the gallery cell before the image loads.'),

                TextInput::make('position')
                    ->numeric()
                    ->default(0)
                    ->helperText('Lower numbers appear first.'),

                Toggle::make('is_zoomable')
                    ->label('Deep zoom')
                    ->helperText('Offers the zoom control in the lightbox.'),

                Toggle::make('is_published')
                    ->label('Published')
                    ->default(true),
            ]),
        ]);
    }
}
