<?php

namespace App\Filament\Resources\Photos\Schemas;

use App\Filament\Forms\Components\CoverImageUpload;
use App\Filament\Forms\Components\LargeFileUpload;
use App\Filament\Support\Translatable;
use App\Models\Category;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class PhotoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Image')->schema([
                CoverImageUpload::make('image')
                    ->collection('image')
                    // Preview the web-sized version, never the file itself. This
                    // field shares its collection with the large-original uploader,
                    // so what it is asked to render may be a gigapixel panorama —
                    // and rendering that here would pull the whole thing into the
                    // browser every time the page opened.
                    ->imageEditor()
                    ->helperText(
                        'For ordinary photos, up to '
                        .round(config('gigapixel.large_file_bytes') / 1048576)
                        .' MB. Panoramas and gigapixel stitches go in "Large original" below. '
                        .'Leave empty and the site shows a labelled placeholder instead of a broken image.'
                    )
                    ->columnSpanFull(),
            ]),

            Section::make('Large original')
                ->description('For panoramas and gigapixel stitches too big for the field above. The browser sends the file in small pieces, so its size is not limited by the server\'s upload settings.')
                ->collapsed(fn ($operation) => $operation !== 'edit')
                ->schema([
                    LargeFileUpload::make('large_original')
                        ->label('Upload a large original')
                        ->helperText('Replaces the current image. Deep zoom tiles are rebuilt automatically afterwards.'),

                    // Tiling runs for minutes after the upload finishes, and this
                    // is the page you are left on when it starts, so the progress
                    // belongs here rather than only in the list.
                    View::make('filament.forms.tiling-status')
                        ->visible(fn ($operation) => $operation === 'edit')
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
