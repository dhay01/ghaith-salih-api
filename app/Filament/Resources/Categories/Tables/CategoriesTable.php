<?php

namespace App\Filament\Resources\Categories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use App\Models\Category;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->reorderable('position')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug')->badge()->color('gray'),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === Category::TYPE_WORK ? 'Gallery' : 'Blog'),
                TextColumn::make('photos_count')->counts('photos')->label('Photos'),
                TextColumn::make('posts_count')->counts('posts')->label('Posts'),
            ])
            ->filters([
                SelectFilter::make('type')->options([
                    Category::TYPE_WORK => 'Gallery filter',
                    Category::TYPE_POST => 'Blog category',
                ]),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
