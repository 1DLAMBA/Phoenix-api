<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Medical Record</title>
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
            padding: 30px 40px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .content {
            padding: 40px;
        }
        .greeting {
            font-size: 16px;
            margin-bottom: 20px;
            color: #2c3e50;
        }
        .info-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 30px;
            margin: 30px 0;
        }
        .info-row {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        .info-row:last-child {
            margin-bottom: 0;
        }
        .info-label {
            display: table-cell;
            width: 40%;
            font-weight: 600;
            color: #5a6c7d;
            font-size: 14px;
            vertical-align: top;
            padding-right: 20px;
        }
        .info-value {
            display: table-cell;
            width: 60%;
            color: #2c3e50;
            font-size: 14px;
            vertical-align: top;
        }
        .details-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e1e8ed;
        }
        .details-label {
            font-weight: 600;
            color: #5a6c7d;
            font-size: 14px;
            margin-bottom: 8px;
        }
        .details-value {
            color: #2c3e50;
            font-size: 14px;
            line-height: 1.6;
            background-color: #ffffff;
            padding: 12px;
            border-radius: 4px;
            border-left: 3px solid #17224d;
        }
        .action-button {
            display: inline-block;
            margin: 24px 0;
            padding: 14px 28px;
            background-color: #17224d;
            color: #ffffff !important;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            border-radius: 6px;
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
                padding: 25px 20px;
            }
            .info-label,
            .info-value {
                display: block;
                width: 100%;
                padding-right: 0;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>New Medical Record</h1>
        </div>
        
        <div class="content">
            <div class="greeting">
                Dear {{ $client->user->name }},
            </div>
            
            <p style="margin: 0 0 20px 0; color: #5a6c7d; font-size: 14px;">
                A new medical record has been created for you. Please review the details below:
            </p>
            
            <div class="info-section">
                <div class="info-row">
                    <div class="info-label">Record Number</div>
                    <div class="info-value">{{ $medicalRecord->record_number }}</div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Healthcare Provider</div>
                    <div class="info-value">
                        @if($professional)
                            @if($doctor)
                                Dr. {{ $professional->user->name }}
                            @else
                                {{ $professional->user->name }}
                            @endif
                        @else
                            N/A
                        @endif
                    </div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Date Created</div>
                    <div class="info-value">
                        {{ \Carbon\Carbon::parse($medicalRecord->created_at)->format('F d, Y \a\t g:i A') }}
                    </div>
                </div>
                
                @if($medicalRecord->diagnosis)
                <div class="details-section">
                    <div class="details-label">Diagnosis</div>
                    <div class="details-value">{{ $medicalRecord->diagnosis }}</div>
                </div>
                @endif
                
                @if($medicalRecord->past_diagnosis)
                <div class="details-section">
                    <div class="details-label">Past Diagnosis</div>
                    <div class="details-value">{{ $medicalRecord->past_diagnosis }}</div>
                </div>
                @endif
                
                @if($medicalRecord->allergies)
                <div class="details-section">
                    <div class="details-label">Allergies</div>
                    <div class="details-value">{{ $medicalRecord->allergies }}</div>
                </div>
                @endif
                
                @if($medicalRecord->treatment)
                <div class="details-section">
                    <div class="details-label">Treatment</div>
                    <div class="details-value">{{ $medicalRecord->treatment }}</div>
                </div>
                @endif
            </div>
            
            @if(!empty($actionUrl))
            <p style="text-align: center; margin: 24px 0;">
                <a href="{{ $actionUrl }}" class="action-button" target="_blank" rel="noopener">View Dashboard</a>
            </p>
            @endif

            <div class="footer-text">
                Please log in to your dashboard to view the complete medical record details.
            </div>
            
            <div class="signature">
                Best regards,<br>
                <strong>Hospital Management System</strong>
            </div>
        </div>
        
        <div class="footer">
            <p style="margin: 0;">This is an automated notification. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>











