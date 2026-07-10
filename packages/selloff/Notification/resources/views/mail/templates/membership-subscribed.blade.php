@extends('selloff-notification::mail.layout')

@section('content')
    <h1 style="text-decoration: none; font-size: 24px; line-height: 28px; font-weight: bold;">
        {{ $data['headline'] ?? 'Membership activated' }}
    </h1>
    <div class="mailcontent">
        <p>Hi {{ $data['firstName'] ?? 'there' }},</p>
        <p>Your <strong>{{ $data['planName'] }}</strong> membership is now active on Selloff.</p>

        <h2 style="margin: 24px 0 10px; font-size: 16px; font-weight: 600;">Subscription details</h2>
        <p style="color: #555;">
            Type: {{ $data['purchaseType'] }}<br>
            Term: {{ $data['termMonths'] }} month{{ ($data['termMonths'] ?? 1) === 1 ? '' : 's' }}<br>
            Amount paid: {{ $data['amountPaid'] }}<br>
            Expires: {{ $data['expiresAt'] }}
        </p>

        @if(!empty($data['membershipUrl']))
            <p style="text-align: center; margin-top: 40px;">
                <span class="btn-primary">
                    <a href="{{ $data['membershipUrl'] }}">{{ $data['buttonText'] ?? 'View membership' }}</a>
                </span>
            </p>
        @endif
    </div>
@endsection
