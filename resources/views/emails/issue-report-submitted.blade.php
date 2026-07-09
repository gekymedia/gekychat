<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>New issue report</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; line-height: 1.5; color: #111;">
    <h2>New issue report #{{ $report->id }}</h2>
    <p><strong>From:</strong> {{ $userLabel }} (user #{{ $report->user_id }})</p>
    <p><strong>Category:</strong> {{ $report->category }} · <strong>Source:</strong> {{ $report->source }}</p>
    <p><strong>Device:</strong> {{ $report->platform }} {{ $report->app_version }} — {{ $report->device_model }} ({{ $report->os_version }})</p>
    @if($report->screen_name)
        <p><strong>Screen:</strong> {{ $report->screen_name }}</p>
    @endif
    <h3>Description</h3>
    <p style="white-space: pre-wrap;">{{ $report->description }}</p>
    <p><a href="{{ $adminUrl }}">Open in admin</a></p>
</body>
</html>
