<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} - KomiBook</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f1f5f9;
            margin: 0;
            padding: 0;
            color: #334155;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            padding: 30px 20px;
            text-align: center;
            color: #ffffff;
        }
        .header h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.025em;
        }
        .content {
            padding: 32px 24px;
            line-height: 1.6;
        }
        .campaign-image {
            width: 100%;
            max-height: 250px;
            object-fit: cover;
            border-radius: 12px;
            margin-bottom: 24px;
        }
        .message-body {
            font-size: 16px;
            color: #334155;
            white-space: pre-wrap;
        }
        .btn {
            display: inline-block;
            background-color: #6366f1;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 32px;
            border-radius: 10px;
            font-weight: 600;
            text-align: center;
            margin: 30px auto 0 auto;
            display: block;
            width: fit-content;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
        }
        .footer {
            background-color: #f8fafc;
            padding: 24px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>KomiBook</h1>
        </div>
        <div class="content">
            <p>Xin chào <strong>{{ $user->name }}</strong>,</p>
            
            @if($imageUrl)
                <img src="{{ $imageUrl }}" alt="Banner" class="campaign-image">
            @endif

            <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; margin-top: 0;">{{ $title }}</h2>
            
            <div class="message-body">{{ $messageContent }}</div>

            <a href="{{ config('app.frontend_url') }}" class="btn">Xem chi tiết trên website</a>
        </div>
        <div class="footer">
            Bạn nhận được email này vì bạn là thành viên của KomiBook.<br>
            © {{ date('Y') }} KomiBook. All rights reserved.
        </div>
    </div>
</body>
</html>
