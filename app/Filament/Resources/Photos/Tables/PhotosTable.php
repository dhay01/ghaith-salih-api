<?php

namespace App\Filament\Resources\Photos\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PhotosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->reorderable('position')
            ->columns([
                SpatieMediaLibraryImageColumn::make('image')
                    ->collection('image')
                    ->conversion('thumb')
                    ->label(''),

                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('category.slug')->label('Category')->badge()->sortable(),
                TextColumn::make('location')->toggleable(),
                TextColumn::make('ratio')->badge()->color('gray'),

                IconColumn::make('is_zoomable')->label('Zoom')->boolean(),
                IconColumn::make('is_published')->label('Live')->boolean(),
            ])
            ->filters([
                SelectFilter::make('category')->relationship('category', 'slug'),
                TernaryFilter::make('is_published')->label('Published'),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
