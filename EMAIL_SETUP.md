# Email Service Setup Guide

## Overview
The email service has been configured to automatically send email notifications to doctors when a new appointment is booked.

## Quick Reference

### Development (Mailtrap)
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_mailtrap_username
MAIL_PASSWORD=your_mailtrap_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=test@example.com
MAIL_FROM_NAME="${APP_NAME}"
```

### Production (cPanel)
```env
MAIL_MAILER=smtp
MAIL_HOST=mail.yourdomain.com
MAIL_PORT=587
MAIL_USERNAME=your-email@yourdomain.com
MAIL_PASSWORD=your_email_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"
```

## Configuration

The email configuration uses environment variables, so you can have different settings for development and production.

### Development/Testing - Mailtrap

For local development and testing, use **Mailtrap** to capture emails without actually sending them:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_mailtrap_username
MAIL_PASSWORD=your_mailtrap_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=test@example.com
MAIL_FROM_NAME="${APP_NAME}"
```

**To get Mailtrap credentials:**
1. Sign up at https://mailtrap.io (free tier available)
2. Create an inbox
3. Go to SMTP Settings
4. Copy the username and password

**Benefits:**
- Emails are captured in Mailtrap's web interface
- No real emails sent during development
- Perfect for testing email templates

### Production - cPanel Email SMTP

For production, use your **cPanel email account** SMTP settings:

```env
MAIL_MAILER=smtp
MAIL_HOST=mail.yourdomain.com
MAIL_PORT=587
MAIL_USERNAME=your-email@yourdomain.com
MAIL_PASSWORD=your_email_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"
```

**Common cPanel SMTP Settings:**
- **Host:** Usually `mail.yourdomain.com` or `smtp.yourdomain.com`
- **Port:** `587` (TLS) or `465` (SSL)
- **Encryption:** `tls` (for port 587) or `ssl` (for port 465)
- **Username:** Your full email address (e.g., `noreply@yourdomain.com`)
- **Password:** The password for that email account

**To find your cPanel SMTP settings:**
1. Log into cPanel
2. Go to **Email Accounts**
3. Click **Connect Devices** or **Configure Mail Client**
4. Look for **Outgoing Server (SMTP)** settings
5. Use those credentials

**Alternative cPanel SMTP Ports:**
- Port `587` with `tls` (recommended)
- Port `465` with `ssl` (alternative)
- Port `25` (usually blocked by hosting providers)

### Other Email Providers

**Gmail:**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="${APP_NAME}"
```
*Note: Requires App Password (not regular password)*

**Mailgun:**
```env
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=your-domain.com
MAILGUN_SECRET=your-mailgun-secret
```

**Postmark:**
```env
MAIL_MAILER=postmark
POSTMARK_TOKEN=your-postmark-token
```

**Amazon SES:**
```env
MAIL_MAILER=ses
AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=us-east-1
```

### For Development/Testing (Log Only)

You can use the log driver to see emails in your log files:
```env
MAIL_MAILER=log
```

## How It Works

1. When an appointment is created via the `/appointment/create` endpoint
2. The system automatically:
   - Loads the doctor and client relationships
   - Sends an email to the doctor's email address
   - Includes appointment details (patient name, date/time, symptoms, etc.)

## Email Template

The email template is located at: `resources/views/emails/appointment-booked.blade.php`

You can customize the email design by editing this file.

## Error Handling

If email sending fails, the error is logged but the appointment creation will still succeed. Check your Laravel logs for any email-related errors.

## Environment-Specific Configuration

### Development (Local)
Use Mailtrap in your local `.env` file:
```env
APP_ENV=local
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
# ... Mailtrap settings
```

### Production
Use cPanel SMTP in your production `.env` file:
```env
APP_ENV=production
MAIL_MAILER=smtp
MAIL_HOST=mail.yourdomain.com
# ... cPanel SMTP settings
```

**Important:** After updating `.env` in production, always clear the config cache:
```bash
php artisan config:clear
php artisan config:cache
```

## Testing

### Testing with Mailtrap (Development)
1. Configure Mailtrap in your `.env` file
2. Create a test appointment through the API
3. Check your Mailtrap inbox at https://mailtrap.io
4. You'll see the email there without sending real emails

### Testing in Production
1. Ensure cPanel SMTP settings are correct in production `.env`
2. Create a test appointment
3. Check the doctor's actual email inbox
4. Check Laravel logs (`storage/logs/laravel.log`) if email fails

### Testing with Log Driver
1. Set `MAIL_MAILER=log` in `.env`
2. Create a test appointment
3. Check `storage/logs/laravel.log` for the email content

