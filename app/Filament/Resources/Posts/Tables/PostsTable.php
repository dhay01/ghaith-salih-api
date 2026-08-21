<?php

namespace App\Filament\Resources\Posts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('published_on', 'desc')
            ->columns([
                SpatieMediaLibraryImageColumn::make('image')->collection('image')->conversion('thumb')->label(''),
                TextColumn::make('title')->searchable()->wrap()->limit(60),
                TextColumn::make('category.slug')->label('Category')->badge(),
                TextColumn::make('published_on')->date('M Y')->sortable(),
                TextColumn::make('read_minutes')->label('Read')->suffix(' min'),
                IconColumn::make('is_featured')->label('Featured')->boolean(),
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
