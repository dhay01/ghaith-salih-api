<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Workshop */
class WorkshopResource extends JsonResource
{
    /**
     * Translatable fields are resolved to the request's locale here, so the
     * frontend receives plain strings and never has to know about the
     * per-locale storage shape.
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'mode' => $this->mode,
            'level' => $this->level,
            'location' => $this->location,
            'overview' => $this->overview,
            'duration' => $this->duration,
            'attendees' => $this->attendees,
            'is_past' => $this->isPast(),
            'images' => $this->imageUrls(),

            'price' => $this->price,
            'price_minor' => $this->price_minor,
            'currency' => $this->currency,

            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),

            'seats_total' => $this->seats_total,
            'seats_left' => $this->seatsLeft(),
            'is_full' => $this->isFull(),
            'accepts_reservations' => $this->canAcceptReservations(),

            'outcomes' => $this->outcomes ?? [],
            'syllabus' => $this->syllabus ?? [],
            'included' => $this->included ?? [],
            'prerequisites' => $this->prerequisites ?? [],
            'faqs' => $this->faqs ?? [],
        ];
    }
}
