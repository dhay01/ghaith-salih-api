<x-mail::message>
# New reservation

**{{ $reservation->name }}** applied for
**{{ $workshop->getTranslation('title', 'en') }}**
({{ $workshop->starts_on?->format('j M Y') }}).

<x-mail::panel>
**Status** — {{ \App\Models\Reservation::STATUSES[$reservation->status] ?? $reservation->status }}<br>
**Seats** — {{ $reservation->seats }}<br>
**Seats left after this** — {{ $workshop->seatsLeft() }} of {{ $workshop->seats_total }}<br>
**Phone** — {{ $reservation->phone }}<br>
**Email** — {{ $reservation->email ?: '—' }}<br>
**Submitted in** — {{ strtoupper($reservation->locale) }}
</x-mail::panel>

## Questionnaire

@foreach ($answers as $question => $answer)
**{{ $question }}**
{{ $answer }}

@endforeach

<x-mail::button :url="config('app.url') . '/admin/reservations/' . $reservation->id">
Open in dashboard
</x-mail::button>
</x-mail::message>
