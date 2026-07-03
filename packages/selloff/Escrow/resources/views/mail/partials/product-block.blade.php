@if($mail->productImageUrl)
<p>
    <a href="{{ $mail->productUrl }}">
        <img src="{{ $mail->productImageUrl }}" alt="{{ $mail->productTitle }}" class="img-product">
    </a>
</p>
@endif
