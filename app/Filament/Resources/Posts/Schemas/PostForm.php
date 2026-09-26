<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Filament\Forms\Components\CoverImageUpload;
use App\Filament\Support\Translatable;
use App\Models\Category;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Post')->columnSpanFull()->tabs([
                Tab::make('Content')->schema([
                    Translatable::text('title', 'Title'),
                    Translatable::textarea('excerpt', 'Excerpt', 2),
                    Translatable::textarea('standfirst', 'Standfirst', 3),
                    self::bodyBuilder(),
                ]),

                Tab::make('Meta')->columns(2)->schema([
                    CoverImageUpload::make('image')
                        ->collection('image')
                        ->label('Cover image')
                        ->columnSpanFull(),

                    Select::make('category_id')
                        ->label('Category')
                        ->relationship(
                            'category',
                            'slug',
                            fn ($query) => $query->where('type', Category::TYPE_POST)->orderBy('position'),
                        )
                        ->searchable()
                        ->preload(),

                    TagsInput::make('tags'),

                    DatePicker::make('published_on')->default(now()),

                    TextInput::make('read_minutes')
                        ->numeric()
                        ->label('Read time (minutes)'),

                    Toggle::make('is_featured')
                        ->label('Featured')
                        ->helperText('The blog index gives the featured post the large hero slot. Only the newest featured post is used.'),

                    Toggle::make('is_published')->label('Published')->default(true),
                ]),
            ]),
        ]);
    }

    /**
     * The block types here are exactly the ones BlogPostPage.vue knows how to
     * render — adding one means adding a renderer on the frontend too.
     */
    protected static function bodyBuilder(): Builder
    {
        return Builder::make('body')
            ->label('Article body')
            ->columnSpanFull()
            ->collapsible()
            ->blocks([
                Block::make('text')
                    ->label('Paragraphs')
                    ->schema([
                        Textarea::make('paragraphs')
                            ->label('Paragraphs (one per line break)')
                            ->rows(6)
                            // Stored as an array so the template can render one <p>
                            // per paragraph without parsing markup.
                            ->formatStateUsing(fn ($state) => is_array($state) ? implode("\n\n", $state) : $state)
                            ->dehydrateStateUsing(fn ($state) => collect(preg_split('/\n\s*\n/', (string) $state))
                                ->map(fn ($p) => trim($p))
                                ->filter()
                                ->values()
                                ->all()),
                    ]),

                Block::make('heading')
                    ->label('Heading')
                    ->schema([TextInput::make('text')]),

                Block::make('quote')
                    ->label('Pull quote')
                    ->schema([Textarea::make('text')->rows(3)]),

                Block::make('figure')
                    ->label('Figure')
                    ->schema([
                        FileUpload::make('path')
                            ->label('Image')
                            ->image()
                            ->maxSize((int) (config('gigapixel.large_file_bytes') / 1024))
                            ->imageResizeMode('max')
                            ->imageResizeTargetWidth(2600)
                            ->imageResizeTargetHeight(2600)
                            ->disk(config('media-library.disk_name'))
                            ->directory('posts'),
                        TextInput::make('ratio')->placeholder('3 / 2'),
                        TextInput::make('caption'),
                    ]),
            ]);
    }
}
