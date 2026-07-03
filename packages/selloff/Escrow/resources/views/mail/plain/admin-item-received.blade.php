{{ $mail->subject }}

Escrow item received by buyer.

Buyer: {{ $mail->buyerName }}
Seller: {{ $mail->sellerName }}
Product: {{ $mail->productTitle }}
@if($mail->deliveryAddress)
Delivery Address: {{ $mail->deliveryAddress }}
@endif
