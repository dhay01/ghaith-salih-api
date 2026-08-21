<?php

namespace App\Filament\Resources\Pages\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')->badge()->sortable(),
                TextColumn::make('title')->wrap()->limit(60),
                TextColumn::make('intro')->wrap()->limit(80)->toggleable(),
            ])
            ->recordActions([EditAction::make()]);
    }
}
