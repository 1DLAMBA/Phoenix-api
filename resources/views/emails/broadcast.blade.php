<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subjectLine }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; line-height: 1.6; color: #2c3e50; background: #f4f6f8; margin: 0; padding: 0; }
        .email-container { max-width: 600px; margin: 0 auto; background: #ffffff; }
        .header { background: #17224d; color: #fff; padding: 24px 40px; text-align: center; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 600; letter-spacing: -0.01em; }
        .banner { display: block; width: 100%; max-width: 600px; height: auto; }
        .content { padding: 32px 40px 12px; font-size: 15px; color: #2c3e50; }
        .headline { margin: 0 0 18px; font-size: 24px; line-height: 1.25; color: #17224d; font-weight: 700; }
        .greeting { font-weight: 600; margin: 0 0 16px; }
        .cta { padding: 4px 40px 36px; }
        .cta a { display: inline-block; background: #17224d; color: #ffffff !important; text-decoration: none; font-weight: 600; font-size: 15px; padding: 13px 26px; border-radius: 10px; }
        .footer { padding: 24px 40px 36px; text-align: center; font-size: 13px; color: #9aa6c4; border-top: 1px solid #eef1f6; }
        @media (max-width: 480px) { .content, .cta, .footer { padding-left: 22px; padding-right: 22px; } }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>Phoenix</h1>
        </div>
        @if(!empty($bannerUrl))
            <img class="banner" src="{{ $bannerUrl }}" alt="">
        @endif
        <div class="content">
            @if(!empty($headline))
                <h2 class="headline">{{ $headline }}</h2>
            @endif
            <p class="greeting">Hi {{ $user->name }},</p>
            {!! $bodyHtml !!}
        </div>
        @if(!empty($ctaLabel) && !empty($ctaUrl))
            <div class="cta"><a href="{{ $ctaUrl }}">{{ $ctaLabel }}</a></div>
        @endif
        <div class="footer">
            You're receiving this because you have a Phoenix account.<br>
            Phoenix — your health assistant.
        </div>
    </div>
</body>
</html>
