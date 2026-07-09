<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Support reply</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; line-height: 1.5; color: #111;">
    <h2>Reply from GekyChat Support</h2>
    <p>Thanks for reporting issue <strong>#{{ $report->id }}</strong>. Our team replied:</p>
    <blockquote style="border-left: 4px solid #6366f1; margin: 16px 0; padding: 8px 16px; background: #f8fafc;">
        {{ $replyMessage }}
    </blockquote>
    <p style="color: #64748b; font-size: 14px;">If the problem continues, you can report again from Settings → Help and feedback → Report a problem.</p>
</body>
</html>
