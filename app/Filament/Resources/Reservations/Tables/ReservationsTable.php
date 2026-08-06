<?php

namespace App\Filament\Resources\Reservations\Tables;

use App\Models\Reservation;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReservationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Received')
                    ->dateTime('j M Y, H:i')
                    ->sortable(),

                TextColumn::make('name')
                    ->searchable()
                    ->weight('medium'),

                TextColumn::make('workshop.title')
                    ->label('Workshop')
                    ->formatStateUsing(fn ($state) => is_array($state) ? ($state['en'] ?? reset($state)) : $state)
                    ->sortable()
                    ->searchable(),

                TextColumn::make('phone')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('email')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),

                TextColumn::make('seats')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Reservation::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        Reservation::STATUS_CONFIRMED => 'success',
                        Reservation::STATUS_PENDING => 'warning',
                        Reservation::STATUS_WAITLISTED => 'info',
                        Reservation::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('locale')
                    ->label('Lang')
                    ->formatStateUsing(fn (string $state) => strtoupper($state))
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(Reservation::STATUSES),

                SelectFilter::make('workshop')
                    ->relationship('workshop', 'slug')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('confirm')
                        ->label('Mark confirmed')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update([
                            'status' => Reservation::STATUS_CONFIRMED,
                            'confirmed_at' => now(),
                        ])),

                    BulkAction::make('export')
                        ->label('Export CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(fn (Collection $records) => static::exportCsv($records)),
                ]),
            ]);
    }

    /**
     * Flattens the questionnaire into columns so the export opens as a normal
     * spreadsheet rather than a column of raw JSON.
     */
    protected static function exportCsv(Collection $records): StreamedResponse
    {
        $rows = $records->load('workshop')->map(function (Reservation $r): SupportCollection {
            return collect([
                'Received' => $r->created_at?->toDateTimeString(),
                'Workshop' => $r->workshop?->getTranslation('title', 'en'),
                'Name' => $r->name,
                'Email' => $r->email,
                'Phone' => $r->phone,
                'Age' => $r->age,
                'Gender' => $r->gender,
                'Seats' => $r->seats,
                'Status' => Reservation::STATUSES[$r->status] ?? $r->status,
                'Language' => strtoupper($r->locale),
            ])->merge($r->answerSummary());
        });

        $headers = $rows->first()?->keys()->all() ?? [];

        return response()->streamDownload(function () use ($rows, $headers) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel reads the Arabic correctly
            fputcsv($out, $headers);

            foreach ($rows as $row) {
                fputcsv($out, array_map(
                    fn (string $h) => $row->get($h, ''),
                    $headers,
                ));
            }

            fclose($out);
        }, 'reservations-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }
}
