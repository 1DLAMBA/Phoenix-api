<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Phoenix - Verify Your Email</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.5;
            color: #2c3e50;
            background-color: #f4f6f8;
            margin: 0;
            padding: 0;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        .header {
            background-color: #17224d;
            color: #ffffff;
            padding: 40px 40px 30px 40px;
            text-align: center;
        }
        .logo {
            max-width: 180px;
            height: auto;
            display: block;
            margin: 0 auto 15px auto;
        }
        .header h1 {
            margin: 15px 0 0 0;
            font-size: 28px;
            font-weight: 600;
        }
        .welcome-badge {
            display: inline-block;
            background-color: rgba(255, 255, 255, 0.2);
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 14px;
            margin-top: 15px;
            font-weight: 500;
        }
        .content {
            padding: 40px;
        }
        .greeting {
            font-size: 18px;
            margin-bottom: 12px;
            color: #2c3e50;
            font-weight: 600;
        }
        .lead {
            font-size: 15px;
            color: #46507a;
            margin: 0 0 28px 0;
        }
        .btn-wrap {
            text-align: center;
            margin: 32px 0;
        }
        .verify-btn {
            display: inline-block;
            background-color: #0055aa;
            color: #ffffff !important;
            text-decoration: none;
            font-size: 17px;
            font-weight: 600;
            padding: 16px 40px;
            border-radius: 8px;
        }
        .fallback {
            font-size: 13px;
            color: #737c9e;
            background: #f8fafd;
            border: 1px solid #e3e8f0;
            border-radius: 8px;
            padding: 16px;
            margin-top: 24px;
            word-break: break-all;
        }
        .fallback a { color: #0055aa; }
        .expiry {
            font-size: 13px;
            color: #737c9e;
            margin-top: 20px;
            text-align: center;
        }
        .footer {
            padding: 24px 40px 40px 40px;
            text-align: center;
            font-size: 13px;
            color: #9aa6c4;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <img src="{{ $message->embed(public_path('images/phoenix-logo.png')) }}" alt="Phoenix" class="logo">
            <h1>Verify your email</h1>
            <span class="welcome-badge">Welcome to Phoenix</span>
        </div>

        <div class="content">
            <p class="greeting">Hi {{ $user->name }},</p>
            <p class="lead">
                Thanks for signing up. Please confirm your email address to activate your Phoenix account.
                Just click the button below.
            </p>

            <div class="btn-wrap">
                <a href="{{ $verificationUrl }}" class="verify-btn" target="_blank" rel="noopener">Verify my email</a>
            </div>

            <p class="expiry">This link expires in 60 minutes.</p>

            <div class="fallback">
                If the button doesn't work, copy and paste this link into your browser:<br>
                <a href="{{ $verificationUrl }}" target="_blank" rel="noopener">{{ $verificationUrl }}</a>
            </div>
        </div>

        <div class="footer">
            If you didn't create a Phoenix account, you can safely ignore this email.
        </div>
    </div>
</body>
</html>
