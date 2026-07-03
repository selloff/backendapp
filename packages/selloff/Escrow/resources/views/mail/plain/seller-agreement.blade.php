{{ $mail->subject }}

Hello {{ $mail->recipientName }},

A buyer wants to purchase "{{ $mail->productTitle }}" through Selloff Escrow.

Confirm your agreement: {{ $mail->agreementUrl }}

Contact: {{ $mail->branding['escrow_email'] }}

Thank you,
Selloff.ng Escrow Team
