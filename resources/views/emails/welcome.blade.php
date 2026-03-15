<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Phoenix</title>
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
        .logo-container {
            margin-bottom: 20px;
        }
        .logo {
            max-width: 180px;
            height: auto;
            display: block;
            margin: 0 auto;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
            margin-top: 15px;
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
            margin-bottom: 20px;
            color: #2c3e50;
            font-weight: 600;
        }
        .welcome-message {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-left: 4px solid #17224d;
            padding: 20px;
            border-radius: 8px;
            margin: 25px 0;
        }
        .welcome-message h2 {
            margin: 0 0 10px 0;
            color: #17224d;
            font-size: 20px;
            font-weight: 600;
        }
        .welcome-message p {
            margin: 0;
            color: #5a6c7d;
            font-size: 14px;
            line-height: 1.6;
        }
        .info-text {
            font-size: 14px;
            color: #5a6c7d;
            margin: 20px 0;
            line-height: 1.6;
        }
        .features-list {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 25px 0;
        }
        .features-list h3 {
            margin: 0 0 15px 0;
            color: #17224d;
            font-size: 16px;
            font-weight: 600;
        }
        .features-list ul {
            margin: 0;
            padding-left: 20px;
            color: #5a6c7d;
            font-size: 14px;
        }
        .features-list li {
            margin-bottom: 8px;
            line-height: 1.5;
        }
        .cta-box {
            background-color: #17224d;
            color: #ffffff;
            padding: 24px;
            border-radius: 8px;
            text-align: center;
            margin: 30px 0;
        }
        .cta-box p {
            margin: 0 0 10px 0;
            font-size: 15px;
        }
        .cta-box .sub {
            font-size: 13px;
            opacity: 0.9;
        }
        .footer-text {
            margin-top: 30px;
            font-size: 14px;
            color: #5a6c7d;
        }
        .signature {
            margin-top: 30px;
            font-size: 14px;
            color: #2c3e50;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 20px 40px;
            text-align: center;
            font-size: 12px;
            color: #7f8c9a;
        }
        @media only screen and (max-width: 600px) {
            .content {
                padding: 30px 20px;
            }
            .header {
                padding: 30px 20px 25px 20px;
            }
            .logo {
                max-width: 150px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <div class="logo-container">
                @php
                    $logoPath = public_path('images/phoenix-logo.png');
                    $logoEmbed = file_exists($logoPath) ? $message->embed($logoPath) : asset('images/phoenix-logo.png');
                @endphp
                <img src="{{ $logoEmbed }}" alt="Phoenix Hospital Management System" class="logo" onerror="this.style.display='none'">
            </div>
            <h1>Welcome to Phoenix!</h1>
            <div class="welcome-badge">Your account is ready</div>
        </div>

        <div class="content">
            <div class="greeting">
                Dear {{ $user->name }},
            </div>

            <div class="welcome-message">
                <h2>You're all set</h2>
                <p>
                    Your registration with Phoenix Hospital Management System is complete. We're glad to have you in our healthcare community. Phoenix is committed to making healthcare more accessible and convenient for everyone.
                </p>
            </div>

            <p class="info-text">
                You can now sign in with your email and password to access your dashboard and use all the features available for your account.
            </p>

            <div class="features-list">
                <h3>What you can do with Phoenix:</h3>
                <ul>
                    <li>Manage appointments seamlessly</li>
                    <li>Access medical records securely</li>
                    <li>Communicate with healthcare professionals</li>
                    <li>Track your health journey</li>
                    <li>Enjoy 24/7 healthcare support</li>
                </ul>
            </div>

            <div class="cta-box">
                <p><strong>Get started</strong></p>
                <p class="sub">Log in to your account and explore the platform.</p>
            </div>

            <div class="footer-text">
                If you have any questions, our support team is here to help. Welcome aboard!
            </div>

            <div class="signature">
                Best regards,<br>
                <strong>The Phoenix Team</strong><br>
                <span style="color: #7f8c9a; font-size: 12px;">Revolutionizing Healthcare with E-health</span>
            </div>
        </div>

        <div class="footer">
            <p style="margin: 0;">This is an automated notification from Phoenix Hospital Management System. Please do not reply to this email.</p>
            <p style="margin: 10px 0 0 0; font-size: 11px;">© {{ date('Y') }} Phoenix Hospital Management System. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
