<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Code</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .code-display {
            font-size: 32px;
            font-weight: bold;
            letter-spacing: 8px;
            text-align: center;
            padding: 20px;
            background: #f0f4f8;
            border-radius: 8px;
            margin: 20px 0;
            color: #1a202c;
            font-family: 'Courier New', monospace;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            font-size: 12px;
            color: #718096;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 style="color: #2d3748; margin-top: 0;">Your Verification Code</h2>
        
        <p>Hello,</p>
        
        <p>Your GekyChat verification code is:</p>
        
        <div class="code-display">{{ $code }}</div>
        
        <p>This code will expire in <strong>10 minutes</strong>.</p>
        
        <p style="color: #718096; font-size: 14px;">
            If you didn't request this code, please ignore this email or contact support if you have concerns about your account security.
        </p>
        
        <div class="footer">
            <p>This is an automated message from GekyChat. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
