<?php

namespace App\Modules\Selloff\Notification\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TransactionalMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $templateData
     * @param  array<string, string>  $branding
     */
    public function __construct(
        public string $mailSubject,
        public string $template,
        public array $templateData,
        public array $branding,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function content(): Content
    {
        $view = $this->resolveView($this->template);

        return new Content(
            html: $view,
            text: 'selloff-notification::mail.plain-text',
            with: [
                'branding' => $this->branding,
                'data' => $this->templateData,
                'subject' => $this->mailSubject,
            ],
        );
    }

    private function resolveView(string $template): string
    {
        $normalized = str_replace('.', '/', $template);
        $candidate = "selloff-notification::mail.templates.{$normalized}";

        return view()->exists($candidate)
            ? $candidate
            : 'selloff-notification::mail.templates.main';
    }
}
