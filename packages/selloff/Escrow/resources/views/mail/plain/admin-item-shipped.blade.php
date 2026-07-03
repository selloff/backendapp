{{ $mail->subject }}

Escrow item shipped.

Buyer: {{ $mail->buyerName }}
Seller: {{ $mail->sellerName }}
Product: {{ $mail->productTitle }}
@if($mail->deliveryAddress)
Delivery Address: {{ $mail->deliveryAddress }}
@endif
