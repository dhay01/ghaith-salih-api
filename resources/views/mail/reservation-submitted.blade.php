@php
    $locale = $reservation->locale;
    $title = $workshop->getTranslation('title', $locale);
    $location = $workshop->getTranslation('location', $locale);
    $waitlisted = $reservation->status === \App\Models\Reservation::STATUS_WAITLISTED;
@endphp

<x-mail::message>
# {{ $waitlisted ? 'You’re on the waitlist' : 'Your seat is reserved' }}

Hi {{ $reservation->name }},

@if ($waitlisted)
**{{ $title }}** is currently full, so we’ve added you to the waitlist. If a seat
frees up we’ll email you straight away — no action needed from you.
@else
Thanks for signing up for **{{ $title }}**. Your place is held.
@endif

<x-mail::panel>
**Dates** — {{ $workshop->starts_on?->format('j F Y') }}@if ($workshop->ends_on && ! $workshop->ends_on->equalTo($workshop->starts_on)) – {{ $workshop->ends_on->format('j F Y') }}@endif<br>
**Location** — {{ $location }}<br>
**Seats** — {{ $reservation->seats }}<br>
**Reference** — {{ str_pad((string) $reservation->id, 5, '0', STR_PAD_LEFT) }}
</x-mail::panel>

@unless ($waitlisted)
There’s nothing to pay now — we’ll send an invoice and the joining details
closer to the date.
@endunless

If anything above looks wrong, just reply to this email.

See you behind the lens,<br>
{{ config('app.name') }}
</x-mail::message>
