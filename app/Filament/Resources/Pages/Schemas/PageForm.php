<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Filament\Support\Translatable;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Page')->schema([
                TextInput::make('key')
                    ->required()
                    ->disabledOn('edit')
                    ->helperText('Matches a route in the site. Fixed once created.'),
            ]),

            Section::make('Header')->schema([
                Translatable::text('eyebrow', 'Eyebrow'),
                Translatable::textarea('title', 'Headline', 2),
                Translatable::textarea('intro', 'Intro', 3),
            ]),

            Section::make('Sections')
                ->description('Named blocks this page renders. The key must match what the template asks for — changing it hides the section.')
                ->schema([
                    Repeater::make('sections')
                        ->label('')
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['key'] ?? null)
                        ->schema([
                            TextInput::make('key')->required(),
                            TextInput::make('eyebrow'),
                            Textarea::make('heading')
                                ->rows(2)
                                ->helperText('Basic HTML is allowed here — <em> renders as the accent style.'),
                            Textarea::make('body')->rows(3),
                            TextInput::make('note'),

                            Repeater::make('items')
                                ->label('Items')
                                ->helperText('Used by list sections such as clients, stats and products.')
                                ->columns(3)
                                ->collapsed()
                                ->schema([
                                    TextInput::make('label'),
                                    TextInput::make('value'),
                                    TextInput::make('note'),
                                ]),
                        ]),
                ]),
        ]);
    }
}
