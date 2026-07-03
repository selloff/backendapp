{{ $mail->subject }}

Hello {{ $mail->recipientName }},

The seller has shipped "{{ $mail->productTitle }}".

Confirm when you receive the item: {{ $mail->agreementUrl }}

Contact: {{ $mail->branding['escrow_email'] }}
