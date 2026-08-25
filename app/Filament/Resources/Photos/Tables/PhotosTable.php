<?php

namespace App\Filament\Resources\Photos\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Models\Photo;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PhotosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Only while work is outstanding: a permanent poll would hammer the
            // database for a table that is otherwise static.
            ->poll(
                Photo::whereIn('dzi_status', [Photo::TILING_QUEUED, Photo::TILING_PROCESSING])->exists()
                    ? '3s'
                    : null,
            )
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

                // Tiling runs in the background for minutes on a large file, so
                // this shows live progress rather than a status that looks stuck.
                ViewColumn::make('dzi_status')
                    ->label('Deep zoom')
                    ->view('filament.tables.columns.tiling-progress'),
                IconColumn::make('is_published')->label('Live')->boolean(),
            ])
            ->filters([
                SelectFilter::make('category')->relationship('category', 'slug'),
                TernaryFilter::make('is_published')->label('Published'),
            ])
            ->recordActions([
                EditAction::make(),

                Action::make('retile')
                    ->label('Rebuild deep zoom')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (Photo $record) => $record->is_zoomable && $record->getFirstMedia('image'))
                    ->requiresConfirmation()
                    ->modalDescription('Re-slices this photo from its original. It runs in the background and can take several minutes.')
                    ->action(function (Photo $record) {
                        $record->forceFill(['dzi_status' => null, 'dzi_media_id' => null])->save();
                        Photo::queueTilingFor($record->fresh());

                        Notification::make()
                            ->title('Rebuild queued')
                            ->body('The tiles will be rebuilt by the queue worker.')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
