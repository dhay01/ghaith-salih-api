<?php

namespace App\Filament\Resources\HeroSlides\Tables;

use App\Filament\Tables\Columns\WebImageColumn;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HeroSlidesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->reorderable('position')
            ->columns([
                WebImageColumn::thumb(),
                TextColumn::make('alt')->label('Alt text'),
                TextColumn::make('position')->sortable(),
                IconColumn::make('is_published')->label('Live')->boolean(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
