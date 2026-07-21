<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReminderDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $sales,
        public Collection $reminders,
    ) {}

    public function envelope(): Envelope
    {
        $count = $this->reminders->count();

        return new Envelope(
            subject: "Anda punya {$count} follow-up yang perlu ditindaklanjuti",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reminder-digest',
            with: [
                'sales'     => $this->sales,
                'reminders' => $this->reminders,
            ],
        );
    }
}
