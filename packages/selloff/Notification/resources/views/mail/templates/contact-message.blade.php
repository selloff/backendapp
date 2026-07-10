@extends('selloff-notification::mail.layout')

@section('content')
    <h1 style="text-decoration: none; font-size: 24px; line-height: 28px; font-weight: bold;">
        {{ $data['title'] ?? 'Contact message' }}
    </h1>
    <div class="mailcontent" style="text-align: left;">
        <p style="color: #555;">
            <strong>Name:</strong> {{ $data['senderName'] ?? '' }}<br>
            <strong>Email:</strong> {{ $data['senderEmail'] ?? '' }}<br>
            @if(!empty($data['subjectLine']))
                <strong>Subject:</strong> {{ $data['subjectLine'] }}<br>
            @endif
        </p>
        <p style="margin-top: 20px; color: #555; white-space: pre-wrap;">{{ $data['messageText'] ?? '' }}</p>

        @if(!empty($data['url']))
            <p style="text-align: center; margin-top: 40px;">
                <span class="btn-primary">
                    <a href="{{ $data['url'] }}">{{ $data['buttonText'] ?? 'View messages' }}</a>
                </span>
            </p>
        @endif
    </div>
@endsection
