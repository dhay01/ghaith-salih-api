<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateReservation;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReservationRequest;
use App\Models\Reservation;
use App\Models\Workshop;
use Illuminate\Http\JsonResponse;

class ReservationController extends Controller
{
    public function store(
        StoreReservationRequest $request,
        Workshop $workshop,
        CreateReservation $createReservation,
    ): JsonResponse {
        if (! $workshop->canAcceptReservations() && ! $workshop->isFull()) {
            return response()->json([
                'message' => 'This workshop is not accepting reservations.',
            ], 422);
        }

        $reservation = $createReservation->handle(
            workshop: $workshop,
            answers: $request->validated('answers'),
            seats: (int) ($request->validated('seats') ?? 1),
            locale: $request->validated('locale') ?? app()->getLocale(),
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json([
            'data' => [
                'reference' => str_pad((string) $reservation->id, 5, '0', STR_PAD_LEFT),
                'status' => $reservation->status,
                'seats' => $reservation->seats,
                'waitlisted' => $reservation->status === Reservation::STATUS_WAITLISTED,
            ],
            'message' => $reservation->status === Reservation::STATUS_WAITLISTED
                ? 'The workshop is full — you have been added to the waitlist.'
                : 'Your seat is reserved.',
        ], 201);
    }
}
