<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Filament\Support\Translatable;
use App\Models\Category;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                Select::make('type')
                    ->required()
                    ->options([
                        Category::TYPE_WORK => 'Gallery filter',
                        Category::TYPE_POST => 'Blog category',
                    ])
                    ->default(Category::TYPE_WORK)
                    ->live(),

                TextInput::make('slug')
                    ->required()
                    ->helperText('Used in URLs and filters. Avoid changing it once the site is live.'),

                TextInput::make('position')
                    ->numeric()
                    ->default(0)
                    ->helperText('Lower numbers appear first.'),
            ]),

            Section::make('Name')->schema([
                Translatable::text('name', 'Name'),
            ]),

            Section::make('Home page showcase')
                ->description('Only used by gallery filters featured on the home page. Leave the span empty to keep a category out of the showcase.')
                ->visible(fn ($get) => $get('type') === Category::TYPE_WORK)
                ->columns(2)
                ->schema([
                    TextInput::make('grid_span')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(12)
                        ->label('Grid span'),

                    TextInput::make('grid_ratio')
                        ->label('Grid ratio')
                        ->placeholder('16/11'),

                    SpatieMediaLibraryFileUpload::make('image')
                        ->collection('image')
                        ->image()
                        ->label('Showcase image')
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
