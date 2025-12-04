<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Phoenix - Email Verification</title>
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
        .otp-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 30px;
            margin: 30px 0;
            text-align: center;
            border: 2px dashed #17224d;
        }
        .otp-label {
            font-size: 14px;
            color: #5a6c7d;
            margin-bottom: 15px;
            font-weight: 500;
        }
        .otp-code {
            font-size: 36px;
            font-weight: 700;
            color: #17224d;
            letter-spacing: 10px;
            margin: 20px 0;
            font-family: 'Courier New', monospace;
            background-color: #ffffff;
            padding: 15px 25px;
            border-radius: 8px;
            display: inline-block;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
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
        .warning-text {
            font-size: 13px;
            color: #e74c3c;
            margin-top: 20px;
            padding: 15px;
            background-color: #fee;
            border-radius: 6px;
            border-left: 4px solid #e74c3c;
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
            .otp-code {
                font-size: 28px;
                letter-spacing: 6px;
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
            <div class="welcome-badge">Your Healthcare Journey Begins</div>
        </div>
        
        <div class="content">
            <div class="greeting">
                Dear {{ $user->name }},
            </div>
            
            <div class="welcome-message">
                <h2>🎉 Welcome to Phoenix Hospital Management System!</h2>
                <p>
                    We're thrilled to have you join our healthcare community. Phoenix is committed to revolutionizing healthcare delivery through innovative digital solutions, making healthcare more accessible and convenient for everyone.
                </p>
            </div>
            
            <p style="margin: 0 0 20px 0; color: #5a6c7d; font-size: 14px; line-height: 1.6;">
                To get started and secure your account, please verify your email address using the verification code below. This is a one-time step to ensure the security of your account.
            </p>
            
            <div class="otp-section">
                <div class="otp-label">Your Email Verification Code</div>
                <div class="otp-code">{{ $otp }}</div>
                <div class="info-text">
                    ⏰ This code will expire in 15 minutes
                </div>
            </div>
            
            <div class="features-list">
                <h3>What You Can Do with Phoenix:</h3>
                <ul>
                    <li>Manage appointments seamlessly</li>
                    <li>Access medical records securely</li>
                    <li>Communicate with healthcare professionals</li>
                    <li>Track your health journey</li>
                    <li>Enjoy 24/7 healthcare support</li>
                </ul>
            </div>
            
            <div class="info-text">
                <strong>Next Steps:</strong> Enter this verification code on the verification page to complete your registration and start using all the features Phoenix has to offer.
            </div>
            
            <div class="warning-text">
                <strong>🔒 Security Notice:</strong> Never share this OTP code with anyone. Our team will never ask for your verification code via email, phone, or any other means.
            </div>
            
            <div class="footer-text">
                If you didn't create an account with Phoenix, please ignore this email or contact our support team immediately.
            </div>
            
            <div class="signature">
                Welcome aboard!<br>
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

