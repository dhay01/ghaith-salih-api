<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Internal notification with the full questionnaire. */
class ReservationReceived extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Reservation $reservation) {}

    public function envelope(): Envelope
    {
        $title = $this->reservation->workshop->getTranslation('title', 'en');

        return new Envelope(
            subject: "New reservation: {$this->reservation->name} — {$title}",
            replyTo: array_filter([$this->reservation->email]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.reservation-received',
            with: [
                'reservation' => $this->reservation,
                'workshop' => $this->reservation->workshop,
                'answers' => $this->reservation->answerSummary(),
            ],
        );
    }
}
