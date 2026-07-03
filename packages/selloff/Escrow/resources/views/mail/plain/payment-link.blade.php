{{ $mail->subject }}

Hello {{ $mail->recipientName }},

Complete payment: {{ $mail->paymentUrl }}

Bank transfer:
{{ $mail->bank['account_number'] ?? '' }}
{{ $mail->bank['bank_name'] ?? '' }}
{{ $mail->bank['account_name'] ?? '' }}

Email proof of payment to {{ $mail->branding['escrow_email'] }}.

Item Price: {{ $mail->formatMoney($mail->pricing['item_price']) }}
Delivery: {{ $mail->formatMoney($mail->pricing['delivery_cost']) }}
Escrow Fee: {{ $mail->formatMoney($mail->pricing['commission_amount']) }}
Total: {{ $mail->formatMoney($mail->pricing['total_amount']) }}

Contact: {{ $mail->branding['escrow_email'] }}
