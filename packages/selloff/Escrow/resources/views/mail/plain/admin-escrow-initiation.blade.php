{{ $mail->subject }}

New escrow agreement initiated.

Buyer: {{ $mail->buyerName }} ({{ $mail->buyerPhone ?? 'N/A' }})
Seller: {{ $mail->sellerName }} ({{ $mail->sellerPhone ?? 'N/A' }})
Product: {{ $mail->productTitle }}
Item Price: {{ $mail->formatMoney($mail->pricing['item_price']) }}
Total: {{ $mail->formatMoney($mail->pricing['total_amount']) }} + delivery charges

{{ $mail->productUrl }}
