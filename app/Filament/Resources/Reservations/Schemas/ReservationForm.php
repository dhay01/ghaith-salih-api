<?php

namespace App\Filament\Resources\Reservations\Schemas;

use App\Models\Reservation;
use App\Models\Workshop;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ReservationForm
{
    /**
     * Two modes, because a reservation reaches this screen two different ways.
     *
     * Editing is triage: the row came from the public form, so the applicant's own
     * answers stay read-only and staff only move `status`. Creating is manual entry
     * for a booking taken by phone or in person, so the same fields become writable
     * and a workshop must be picked — without one the insert violates a NOT NULL
     * constraint, which is exactly what the generated Create page used to do.
     */
    public static function configure(Schema $schema): Schema
    {
        // Applicant details are only editable while creating; on edit they are a
        // record of what the applicant themselves submitted.
        $readOnlyOnEdit = fn (string $operation): bool => $operation === 'edit';

        return $schema
            ->components([
                Section::make('Workshop')
                    ->schema([
                        Select::make('workshop_id')
                            ->label('Workshop')
                            ->relationship('workshop', 'slug', fn ($query) => $query->orderByDesc('starts_on'))
                            ->getOptionLabelFromRecordUsing(fn (Workshop $w) => sprintf(
                                '%s — %s (%d of %d seats left)',
                                $w->getTranslation('title', 'en'),
                                $w->starts_on?->format('d M Y') ?? 'unscheduled',
                                $w->seatsLeft(),
                                $w->seats_total,
                            ))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled($readOnlyOnEdit)
                            // A disabled field is not dehydrated, so on edit the
                            // existing value must be preserved explicitly.
                            ->dehydrated(),
                    ]),

                Section::make('Triage')
                    ->description('The only fields staff should change once a reservation exists.')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('status')
                                ->options(Reservation::STATUSES)
                                ->required()
                                ->native(false)
                                // Set by the seat check on create, not by hand.
                                ->hiddenOn('create'),

                            DateTimePicker::make('confirmed_at')
                                ->label('Confirmed at')
                                ->seconds(false)
                                ->hiddenOn('create'),
                        ]),
                    ])
                    ->hiddenOn('create'),

                Section::make('Applicant')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->required()
                                ->disabled($readOnlyOnEdit),

                            TextInput::make('phone')
                                ->required()
                                ->disabled($readOnlyOnEdit),

                            TextInput::make('email')
                                ->label('Email address')
                                ->email()
                                ->disabled($readOnlyOnEdit)
                                ->helperText(fn (string $operation) => $operation === 'create'
                                    ? 'Optional. Without it the applicant cannot be emailed.'
                                    : null),

                            TextInput::make('age')
                                ->numeric()
                                ->required()
                                ->disabled($readOnlyOnEdit),

                            Select::make('gender')
                                ->options(['male' => 'Male', 'female' => 'Female'])
                                ->native(false)
                                ->required()
                                ->disabled($readOnlyOnEdit)
                                ->visibleOn('create'),

                            TextInput::make('gender')
                                ->disabled()
                                ->visibleOn('edit'),

                            TextInput::make('seats')
                                ->numeric()
                                ->minValue(1)
                                ->default(1)
                                ->required()
                                ->disabled($readOnlyOnEdit)
                                ->helperText(fn (string $operation) => $operation === 'create'
                                    ? 'If the workshop has fewer seats left, the booking is waitlisted instead.'
                                    : null),
                        ]),
                    ]),

                Section::make('Notes')
                    ->visibleOn('create')
                    ->schema([
                        Textarea::make('answers.motivation')
                            ->label('Motivation / notes')
                            ->rows(3)
                            ->helperText('The public form asks eleven questions. For a manual booking only this one is kept — the rest stay blank.'),

                        Toggle::make('send_confirmation')
                            ->label('Email the applicant a confirmation')
                            ->helperText('Off by default: a booking taken by phone has usually been confirmed already.')
                            ->default(false),
                            // Deliberately dehydrated: the page reads it from the
                            // form data, and never mass-assigns that data to the
                            // model, so the non-column key is harmless.
                    ]),

                Section::make('Questionnaire')
                    ->description(fn (?Reservation $record) => $record
                        ? 'Answers as submitted, rendered against question set '.$record->question_set_version
                        : null)
                    // TextEntry, not Text: only an entry carries a label, and the
                    // state is passed explicitly because these keys are question
                    // wording rather than model attributes.
                    ->schema(fn (?Reservation $record) => $record
                        ? collect($record->answerSummary())
                            ->values()
                            ->zip(collect($record->answerSummary())->keys())
                            ->map(fn (\Illuminate\Support\Collection $pair, int $i) => TextEntry::make('answer_'.$i)
                                ->label($pair[1])
                                ->state($pair[0]))
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
