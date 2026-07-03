{{ $mail->subject }}

Hello {{ $mail->recipientName }},

You have indicated that you would like to purchase "{{ $mail->productTitle }}" through Selloff Escrow.

Item Price: {{ $mail->formatMoney($mail->pricing['item_price']) }}
Escrow Fee: {{ $mail->formatMoney($mail->pricing['commission_amount']) }}
Total: {{ $mail->formatMoney($mail->pricing['total_amount']) }} + delivery charges (You will be notified shortly).

Confirm your agreement: {{ $mail->agreementUrl }}

Contact: {{ $mail->branding['escrow_email'] }}

Thank you,
Selloff.ng Escrow Team
