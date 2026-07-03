<!doctype html>
<html>
<head>
    <meta name="viewport" content="width=device-width"/>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <title>{{ $mail->subject }}</title>
    <style>
        img { border: none; max-width: 100%; height: auto; }
        body { background-color: #f6f6f6; font-family: Arial, Helvetica, sans-serif; font-size: 14px; line-height: 1.5; margin: 0; padding: 0; }
        table { border-collapse: collapse; width: 100%; }
        .container { max-width: 600px; margin: 0 auto; padding: 10px; }
        .main { background: #ffffff; border-radius: 4px; border-top: 3px solid {{ $mail->branding['primary'] }}; }
        .wrapper { padding: 28px 24px; }
        .mailcontent { line-height: 26px; font-size: 14px; color: #444444; }
        .mailcontent h2 { font-size: 20px; color: #333333; margin: 0 0 16px; }
        .footer { text-align: center; padding: 16px; color: #999999; font-size: 12px; }
        .footer a { color: {{ $mail->branding['primary'] }}; text-decoration: none; }
        .img-product { max-width: 280px; margin: 12px 0; }
    </style>
</head>
<body>
<table role="presentation" class="body" width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td align="center">
            <div class="container">
                @include('selloff-escrow::mail.partials.header')
                <table role="presentation" class="main" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="wrapper">
                            @yield('content')
                        </td>
                    </tr>
                </table>
                @include('selloff-escrow::mail.partials.footer')
            </div>
        </td>
    </tr>
</table>
</body>
</html>
