<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Appointment Booking</title>
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
        .symptoms-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e1e8ed;
        }
        .symptoms-label {
            font-weight: 600;
            color: #5a6c7d;
            font-size: 14px;
            margin-bottom: 8px;
        }
        .symptoms-value {
            color: #2c3e50;
            font-size: 14px;
            line-height: 1.6;
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
            <h1>New Appointment Booking</h1>
        </div>
        
        <div class="content">
            <div class="greeting">
                Dear Dr. {{ $doctor->user->name }},
            </div>
            
            <p style="margin: 0 0 20px 0; color: #5a6c7d; font-size: 14px;">
                You have received a new appointment booking. Please review the details below:
            </p>
            
            <div class="info-section">
                <div class="info-row">
                    <div class="info-label">Patient Name</div>
                    <div class="info-value">{{ $client->user->name }}</div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Patient Email</div>
                    <div class="info-value">{{ $client->user->email }}</div>
                </div>
                
                @if($client->user->phoneno)
                <div class="info-row">
                    <div class="info-label">Patient Phone</div>
                    <div class="info-value">{{ $client->user->phoneno }}</div>
                </div>
                @endif
                
                <div class="info-row">
                    <div class="info-label">Appointment Date & Time</div>
                    <div class="info-value">
                        @if($appointment->date_time)
                            {{ \Carbon\Carbon::parse($appointment->date_time)->format('F d, Y \a\t g:i A') }}
                        @else
                            <span style="color: #7f8c9a;">To be scheduled</span>
                        @endif
                    </div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Status</div>
                    <div class="info-value">{{ ucfirst($appointment->status) }}</div>
                </div>
                
                @if($appointment->symptoms)
                <div class="symptoms-section">
                    <div class="symptoms-label">Symptoms/Reason</div>
                    <div class="symptoms-value">{{ $appointment->symptoms }}</div>
                </div>
                @endif
            </div>
            
            <div class="footer-text">
                Please log in to your dashboard to view and manage this appointment.
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

