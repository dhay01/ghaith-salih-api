<?php

namespace App\Filament\Resources\Workshops\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class WorkshopForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('slug')
                    ->required(),
                Textarea::make('title')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('mode')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('level')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('location')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('overview')
                    ->columnSpanFull(),
                Textarea::make('outcomes')
                    ->columnSpanFull(),
                Textarea::make('syllabus')
                    ->columnSpanFull(),
                Textarea::make('included')
                    ->columnSpanFull(),
                Textarea::make('prerequisites')
                    ->columnSpanFull(),
                Textarea::make('faqs')
                    ->columnSpanFull(),
                TextInput::make('price_minor')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('currency')
                    ->required()
                    ->default('USD'),
                TextInput::make('seats_total')
                    ->required()
                    ->numeric()
                    ->default(10),
                DatePicker::make('starts_on')
                    ->required(),
                DatePicker::make('ends_on'),
                Toggle::make('is_published')
                    ->required(),
                Toggle::make('accepts_reservations')
                    ->required(),
            ]);
    }
}
