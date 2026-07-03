<?php

namespace App\Modules\Selloff\Escrow\Mail;

use App\Modules\Selloff\Escrow\Support\EscrowMailViewData;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EscrowStageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public EscrowMailViewData $data,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->data->subject);
    }

    public function content(): Content
    {
        return new Content(
            html: $this->data->stage->htmlView(),
            text: $this->data->stage->textView(),
            with: ['mail' => $this->data],
        );
    }
}
