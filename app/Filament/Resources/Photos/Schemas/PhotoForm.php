<?php

namespace App\Filament\Resources\Photos\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PhotoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('slug')
                    ->required(),
                Select::make('category_id')
                    ->relationship('category', 'name'),
                Textarea::make('title')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('location')
                    ->columnSpanFull(),
                Textarea::make('gear')
                    ->columnSpanFull(),
                Textarea::make('alt')
                    ->columnSpanFull(),
                TextInput::make('ratio')
                    ->required()
                    ->default('3/2'),
                Toggle::make('is_zoomable')
                    ->required(),
                TextInput::make('dzi_path'),
                TextInput::make('position')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_published')
                    ->required(),
            ]);
    }
}
