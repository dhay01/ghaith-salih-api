<?php

namespace App\Filament\Resources\Workshops\Schemas;

use App\Filament\Forms\Components\CoverImageUpload;
use App\Filament\Support\Translatable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class WorkshopForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Workshop')->columnSpanFull()->tabs([
                Tab::make('Details')->schema([
                    CoverImageUpload::make('image')
                        ->collection('image')
                        ->label('Cover image'),

                    Translatable::text('title', 'Title'),
                    Translatable::text('mode', 'Format'),
                    Translatable::text('level', 'Level'),
                    Translatable::text('location', 'Location'),
                    Translatable::text('duration', 'Duration'),
                    Translatable::textarea('overview', 'Overview', 4),
                ]),

                // These columns cannot be null. Left blank, they used to reach the
                // database as null and the save failed with a server error rather
                // than a message on the field.
                Tab::make('Schedule & seats')->columns(2)->schema([
                    DatePicker::make('starts_on')->label('Start date')->required(),
                    DatePicker::make('ends_on')->label('End date'),

                    TextInput::make('price_minor')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->label('Price (minor units)')
                        ->helperText('In cents — 48000 is $480.00. Leave at 0 for a free workshop.')
                        ->dehydrateStateUsing(fn ($state) => filled($state) ? (int) $state : 0),
                    TextInput::make('currency')->maxLength(3)->default('USD')->required(),

                    TextInput::make('seats_total')->numeric()->minValue(1)->default(10)->required(),
                    Translatable::text('attendees', 'Attendance note'),

                    Toggle::make('is_published')->label('Published'),
                    Toggle::make('accepts_reservations')->label('Accepts reservations'),
                ]),

                Tab::make('Curriculum')->schema([
                    self::simpleList('outcomes', 'What you’ll learn'),
                    self::simpleList('included', 'Included'),
                    self::simpleList('prerequisites', 'Prerequisites'),

                    Repeater::make('syllabus')
                        ->defaultItems(0)
                        ->collapsed()
                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? $state['day'] ?? null)
                        ->schema([
                            TextInput::make('day')->placeholder('Day 01'),
                            TextInput::make('title'),
                            Repeater::make('slots')
                                ->defaultItems(0)
                                ->columns(2)
                                ->schema([
                                    TextInput::make('time')->placeholder('09:00'),
                                    TextInput::make('what')->label('Session'),
                                ]),
                        ]),

                    Repeater::make('faqs')
                        ->defaultItems(0)
                        ->collapsed()
                        ->itemLabel(fn (array $state): ?string => $state['q'] ?? null)
                        ->schema([
                            TextInput::make('q')->label('Question'),
                            Textarea::make('a')->label('Answer')->rows(3),
                        ]),
                ]),
            ]),
        ]);
    }

    protected static function simpleList(string $name, string $label): Repeater
    {
        return Repeater::make($name)
            ->label($label)
            ->defaultItems(0)
            ->simple(TextInput::make('value'));
    }
}
