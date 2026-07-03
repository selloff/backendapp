{{ $mail->subject }}

Hello {{ $mail->recipientName }},

The buyer has paid for "{{ $mail->productTitle }}". Please ship the item.

@if($mail->deliveryAddress)
Delivery Address: {{ $mail->deliveryAddress }}
@endif

Confirm shipment: {{ $mail->agreementUrl }}

Contact: {{ $mail->branding['escrow_email'] }}
