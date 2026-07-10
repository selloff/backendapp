@extends('selloff-notification::mail.layout')

@section('content')
    <h1 style="text-decoration: none; font-size: 24px; line-height: 28px; font-weight: bold;">
        {{ $data['title'] ?? 'Membership expired' }}
    </h1>
    <div class="mailcontent">
        <p>Hello,</p>
        <p>Your <strong>{{ $data['planName'] ?? 'membership plan' }}</strong> membership expired on {{ $data['expiresAt'] ?? 'recently' }}.</p>
        <p>Renew your plan to continue enjoying vendor benefits such as listing products and promotions.</p>

        @if(!empty($data['renewUrl']))
            <p style="text-align: center; margin-top: 40px;">
                <span class="btn-primary">
                    <a href="{{ $data['renewUrl'] }}">{{ $data['buttonText'] ?? 'Renew now' }}</a>
                </span>
            </p>
        @endif
    </div>
@endsection
