{{ $mail->subject }}

Hello {{ $mail->recipientName }},

We have recorded your escrow payment for "{{ $mail->productTitle }}". The seller will ship your item soon.

Total paid: {{ $mail->formatMoney($mail->pricing['total_amount']) }}

Contact: {{ $mail->branding['escrow_email'] }}
