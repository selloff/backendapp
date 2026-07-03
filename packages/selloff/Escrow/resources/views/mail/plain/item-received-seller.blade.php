{{ $mail->subject }}

Hello {{ $mail->recipientName }},

The buyer confirmed receipt of "{{ $mail->productTitle }}".

Your payment will be processed within 24 hours.

Contact: {{ $mail->branding['escrow_email'] }}
