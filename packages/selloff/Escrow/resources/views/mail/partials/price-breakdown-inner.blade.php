<p style="background-color: {{ $mail->branding['muted_box'] }}; padding: 20px; border-radius: 4px;">
    @if(isset($showParties) && $showParties)
        <strong>Buyer:</strong> {{ $mail->buyerName }} (Tel: {{ $mail->buyerPhone ?? 'N/A' }}, Username: {{ $mail->buyerUsername ?? 'N/A' }})<br>
        <strong>Seller:</strong> {{ $mail->sellerName }} (Tel: {{ $mail->sellerPhone ?? 'N/A' }}, Username: {{ $mail->sellerUsername ?? 'N/A' }})<br>
    @endif
    <strong>Item Price:</strong> {{ $mail->formatMoney($mail->pricing['item_price']) }}<br>
    @if($mail->deliveryCostPending)
        <strong>Delivery cost:</strong> We will communicate with you shortly about this (our Escrow department will contact you).<br>
    @elseif(($mail->pricing['delivery_cost'] ?? 0) > 0)
        <strong>Delivery cost:</strong> {{ $mail->formatMoney($mail->pricing['delivery_cost']) }}<br>
    @endif
    <strong>Escrow Fee:</strong> {{ $mail->formatMoney($mail->pricing['commission_amount']) }}
    @if(($mail->pricing['commission_rate'] ?? 0) > 0)
        ({{ $mail->pricing['commission_rate'] }}% of Item price)
    @endif
    <br>
    <strong>Total Amount:</strong>
    <span style="color: {{ $mail->branding['alert'] }};">
        {{ $mail->formatMoney($mail->pricing['total_amount']) }}
        @if($mail->deliveryCostPending)
            + delivery charges (You will be notified shortly).
        @elseif(isset($adminDeliveryNote) && $adminDeliveryNote)
            + delivery charges (Please liaise with both parties).
        @endif
    </span>
</p>
