<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Confirmation sent to the applicant. */
class ReservationSubmitted extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Reservation $reservation) {}

    public function envelope(): Envelope
    {
        $title = $this->reservation->workshop->getTranslation('title', $this->reservation->locale);

        return new Envelope(
            subject: $this->reservation->status === Reservation::STATUS_WAITLISTED
                ? "You're on the waitlist — {$title}"
                : "Your seat is reserved — {$title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.reservation-submitted',
            with: [
                'reservation' => $this->reservation,
                'workshop' => $this->reservation->workshop,
            ],
        );
    }
}
