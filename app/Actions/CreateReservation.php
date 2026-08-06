<?php

namespace App\Actions;

use App\Mail\ReservationReceived;
use App\Mail\ReservationSubmitted;
use App\Models\Reservation;
use App\Models\Workshop;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class CreateReservation
{
    /**
     * Persist a reservation and fan out the notifications.
     *
     * Answers that map to a real column are lifted out of the JSON blob so the
     * dashboard can filter and export on them; the full set is still stored in
     * `answers` so nothing is lost if the question set changes later.
     */
    public function handle(Workshop $workshop, array $answers, int $seats, string $locale, ?string $ip, ?string $userAgent): Reservation
    {
        $version = config('reservation_questions.current');
        $columns = $this->liftColumns($answers, $version);

        $reservation = DB::transaction(function () use ($workshop, $answers, $columns, $seats, $locale, $version, $ip, $userAgent) {
            // Lock the workshop row so two concurrent submissions cannot both
            // read the same remaining-seat count and oversell the room.
            $locked = Workshop::whereKey($workshop->getKey())->lockForUpdate()->firstOrFail();

            $status = $locked->seatsLeft() >= $seats
                ? Reservation::STATUS_PENDING
                : Reservation::STATUS_WAITLISTED;

            return $locked->reservations()->create([
                ...$columns,
                'seats' => $seats,
                'answers' => $answers,
                'question_set_version' => $version,
                'status' => $status,
                'locale' => $locale,
                'ip' => $ip,
                'user_agent' => $userAgent,
            ]);
        });

        $reservation->load('workshop');

        if ($reservation->email) {
            Mail::to($reservation->email)->queue(new ReservationSubmitted($reservation));
        }

        if ($admin = config('mail.admin_address')) {
            Mail::to($admin)->queue(new ReservationReceived($reservation));
        }

        return $reservation;
    }

    /** @return array<string, mixed> */
    private function liftColumns(array $answers, string $version): array
    {
        $columns = [];

        foreach (config('reservation_questions.'.$version, []) as $q) {
            if (isset($q['column']) && array_key_exists($q['id'], $answers)) {
                $columns[$q['column']] = $answers[$q['id']];
            }
        }

        return $columns;
    }
}
