<?php

namespace App\Filament\Resources\Reservations\Schemas;

use App\Models\Reservation;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;

class ReservationForm
{
    /**
     * Reservations arrive from the public API, so this screen is for triage
     * rather than data entry: status is the only field staff change, and the
     * applicant's own answers are shown read-only.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Triage')
                    ->description('The only fields staff should change.')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('status')
                                ->options(Reservation::STATUSES)
                                ->required()
                                ->native(false),

                            DateTimePicker::make('confirmed_at')
                                ->label('Confirmed at')
                                ->seconds(false),
                        ]),
                    ]),

                Section::make('Applicant')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')->disabled(),
                            TextInput::make('phone')->disabled(),
                            TextInput::make('email')->label('Email address')->disabled(),
                            TextInput::make('age')->numeric()->disabled(),
                            TextInput::make('gender')->disabled(),
                            TextInput::make('seats')->numeric()->disabled(),
                        ]),
                    ]),

                Section::make('Questionnaire')
                    ->description(fn (?Reservation $record) => $record
                        ? 'Answers as submitted, rendered against question set '.$record->question_set_version
                        : null)
                    ->schema(fn (?Reservation $record) => $record
                        ? collect($record->answerSummary())
                            ->map(fn (string $answer, string $question) => Text::make($answer)
                                ->label($question))
                            ->values()
                            ->all()
                        : [])
                    ->hidden(fn (?Reservation $record) => $record === null),

                Section::make('Origin')
                    ->collapsed()
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('locale')->label('Submitted in')->disabled(),
                            TextInput::make('ip')->label('IP address')->disabled(),
                            TextInput::make('question_set_version')->label('Question set')->disabled(),
                        ]),
                    ])
                    ->hidden(fn (?Reservation $record) => $record === null),
            ]);
    }
}
