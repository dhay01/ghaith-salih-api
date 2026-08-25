<?php

namespace App\Filament\Resources\Reservations\Pages;

use App\Actions\CreateReservation as CreateReservationAction;
use App\Filament\Resources\Reservations\ReservationResource;
use App\Models\Workshop;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateReservation extends CreateRecord
{
    protected static string $resource = ReservationResource::class;

    protected static ?string $title = 'Add a manual reservation';

    /** Set from the form toggle before the record is written. */
    protected bool $notifyApplicant = false;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->notifyApplicant = (bool) ($data['send_confirmation'] ?? false);
        unset($data['send_confirmation']);

        return $data;
    }

    /**
     * Writing the row directly would skip the seat lock, the waitlist fallback and
     * the question-set stamp, so manual entry goes through the same action the
     * public endpoint uses.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $workshop = Workshop::findOrFail($data['workshop_id']);

        // Rebuild the answer map the action expects: identity fields are lifted
        // back out into columns by the action itself.
        $answers = array_filter([
            'name' => $data['name'] ?? null,
            'gender' => $data['gender'] ?? null,
            'age' => isset($data['age']) ? (int) $data['age'] : null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'motivation' => $data['answers']['motivation'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        return app(CreateReservationAction::class)->handle(
            workshop: $workshop,
            answers: $answers,
            seats: max(1, (int) ($data['seats'] ?? 1)),
            locale: app()->getLocale(),
            ip: null,
            userAgent: 'dashboard (manual entry)',
            notifyApplicant: $this->notifyApplicant,
        );
    }
}
