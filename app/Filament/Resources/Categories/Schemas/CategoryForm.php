<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Filament\Support\Translatable;
use App\Models\Category;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                Select::make('type')
                    ->options([
                        Category::TYPE_WORK => 'Gallery filter',
                        Category::TYPE_POST => 'Blog category',
                    ])
                    ->default(Category::TYPE_WORK)
                    ->live(),

                TextInput::make('slug')
                    ->helperText('Used in URLs and filters. Avoid changing it once the site is live.'),

                TextInput::make('position')
                    ->numeric()
                    ->default(0)
                    ->helperText('Lower numbers appear first.'),
            ]),

            Section::make('Name')->schema([
                Translatable::text('name', 'Name'),
            ]),

            Section::make('Home page')
                ->visible(fn ($get) => $get('type') === Category::TYPE_WORK)
                ->schema([
                    Toggle::make('show_on_home')
                        ->label('Show on the home page')
                        ->helperText('Adds this category to “Featured galleries”. Up to four are shown, using each category\'s newest photo.'),

                    // The hand-set showcase this switch replaced, kept to go back to.
                    // Restoring it also needs `use App\Filament\Forms\Components\CoverImageUpload;`
                    // and the home page reading grid_span and grid_ratio again.
                    //
                    // TextInput::make('grid_span')
                    //     ->numeric()
                    //     ->minValue(1)
                    //     ->maxValue(12)
                    //     ->label('Tile width')
                    //     ->helperText('Out of 12 across a row: 7 and 5 share a row, 12 takes a row alone. A row that comes up short is widened to fill the page.'),
                    //
                    // TextInput::make('grid_ratio')
                    //     ->label('Tile shape')
                    //     ->placeholder('16/11')
                    //     ->helperText('Width/height, such as 16/11 or 4/5; 3/2 when empty. Tiles in a row share one height, capped at 40% of the screen.'),
                    //
                    // CoverImageUpload::make('image')
                    //     ->collection('image')
                    //     ->label('Showcase image')
                    //     ->helperText('Optional. The tile shows this category\'s newest photograph; this image is only used while the category has none.')
                    //     ->columnSpanFull(),
                ]),
        ]);
    }
}
