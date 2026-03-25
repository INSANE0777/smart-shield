<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Contact Form Submission</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .content {
            background-color: #ffffff;
            padding: 20px;
            border: 1px solid #e9ecef;
            border-radius: 5px;
        }
        .field {
            margin-bottom: 15px;
        }
        .field-label {
            font-weight: bold;
            color: #495057;
            margin-bottom: 5px;
        }
        .field-value {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 3px;
            border-left: 3px solid #007bff;
        }
        .message-content {
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .footer {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
            font-size: 12px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>New Contact Form Submission</h2>
        <p>You have received a new message through the SMART SHIELD UI contact form.</p>
    </div>

    <div class="content">
        <div class="field">
            <div class="field-label">From:</div>
            <div class="field-value">{{ e($email ?? 'N/A') }}</div>
        </div>

        <div class="field">
            <div class="field-label">Subject:</div>
            <div class="field-value">{{ e($subject ?? 'N/A') }}</div>
        </div>

        <div class="field">
            <div class="field-label">Message:</div>
            <div class="field-value">
                <div class="message-content">{{ e($messageContent ?? $message ?? 'N/A') }}</div>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>This message was sent from the SMART SHIELD UI contact form at {{ url('/contact') }}</p>
        <p>Sent on: {{ date('F j, Y \a\t g:i A T') }}</p>
    </div>
</body>
</html>
