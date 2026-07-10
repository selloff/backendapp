@if(!empty($data['lineItems']))
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="text-align: left; width: 100%; margin-top: 24px;" class="table-products">
        <tr>
            <th style="padding: 10px 0; border-bottom: 2px solid #ddd;">Product</th>
            <th style="padding: 10px 0; border-bottom: 2px solid #ddd;">Unit price</th>
            <th style="padding: 10px 0; border-bottom: 2px solid #ddd;">Qty</th>
            <th style="padding: 10px 0; border-bottom: 2px solid #ddd;">VAT</th>
            <th style="padding: 10px 0; border-bottom: 2px solid #ddd;">Total</th>
        </tr>
        @foreach($data['lineItems'] as $line)
            <tr>
                <td style="width: 40%; padding: 15px 0; border-bottom: 1px solid #ddd;">{{ $line['title'] }}</td>
                <td style="padding: 12px 2px; border-bottom: 1px solid #ddd;">{{ $line['unitPrice'] }}</td>
                <td style="padding: 12px 2px; border-bottom: 1px solid #ddd;">{{ $line['quantity'] }}</td>
                <td style="padding: 12px 2px; border-bottom: 1px solid #ddd;">
                    @if(!empty($line['vat']))
                        {{ $line['vat'] }} ({{ $line['vatRate'] }}%)
                    @else
                        -
                    @endif
                </td>
                <td style="padding: 12px 2px; border-bottom: 1px solid #ddd;">{{ $line['total'] }}</td>
            </tr>
        @endforeach
    </table>
@endif
