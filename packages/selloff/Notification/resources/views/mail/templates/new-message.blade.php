@extends('selloff-notification::mail.layout')

@section('content')
    <h1 style="text-decoration: none; font-size: 20px; line-height: 28px; font-weight: bold; margin-bottom: 5px;">
        {{ $data['title'] ?? 'You have a new message' }}
    </h1>
    <div class="mailcontent">
        <p style="text-align: left; margin-bottom: 10px;">
            <strong style="font-weight: 600;">User</strong>: {{ $data['messageSender'] ?? 'A user' }}
        </p>
        @if(!empty($data['messageSubject']))
            <p style="text-align: left; margin-bottom: 10px;">
                <strong style="font-weight: 600;">Subject</strong>: {{ $data['messageSubject'] }}
            </p>
        @endif
        <p style="text-align: left; margin-bottom: 10px;">
            <strong style="font-weight: 600;">Message</strong>:<br>{{ $data['messageText'] ?? '' }}
        </p>
        @if(!empty($data['url']))
            <p style="text-align: center; margin-top: 60px;">
                <span class="btn-primary">
                    <a href="{{ $data['url'] }}">{{ $data['buttonText'] ?? 'Messages' }}</a>
                </span>
            </p>
        @endif
    </div>
@endsection
