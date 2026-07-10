<?php

namespace App\Modules\Selloff\Notification\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, string>  $branding
     */
    public function __construct(
        public string $mailSubject,
        public array $branding,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function content(): Content
    {
        return new Content(
            html: 'selloff-notification::mail.templates.test',
            text: 'selloff-notification::mail.plain-text',
            with: [
                'branding' => $this->branding,
                'data' => [
                    'title' => 'Test email',
                    'content' => 'This is a test email from Selloff. Your mail transport is configured correctly.',
                ],
                'subject' => $this->mailSubject,
            ],
        );
    }
}
