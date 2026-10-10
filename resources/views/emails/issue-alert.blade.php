<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width">
<title>{{ $reason }}: {{ $issue->title }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#18181b;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f4f5;padding:32px 16px;">
<tr><td align="center">
<table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e4e4e7;">
    <tr>
        <td style="padding:20px 28px;background:#09090b;color:#fafafa;font-size:14px;">
            <strong style="font-size:16px;letter-spacing:-0.01em;">Loggerxo</strong>
            <span style="opacity:.6;"> &nbsp;·&nbsp; {{ $project?->name }}</span>
        </td>
    </tr>
    <tr>
        <td style="padding:28px;">
            @php
                $color = match ($issue->level) {
                    'emergency', 'alert', 'critical' => '#e11d48',
                    'error' => '#dc2626',
                    'warning' => '#d97706',
                    default => '#2563eb',
                };
            @endphp
            <div style="display:inline-block;padding:4px 10px;border-radius:999px;background:{{ $color }};color:#fff;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">{{ $reason }}</div>
            <h1 style="margin:16px 0 6px;font-size:20px;line-height:1.3;letter-spacing:-0.01em;">{{ $issue->title }}</h1>
            @if($issue->culprit)
                <div style="font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:13px;color:#52525b;word-break:break-all;">{{ $issue->culprit }}</div>
            @endif

            <table role="presentation" cellspacing="0" cellpadding="0" style="margin:22px 0;width:100%;border-collapse:collapse;font-size:14px;">
                <tr>
                    <td style="padding:8px 0;color:#71717a;width:140px;border-top:1px solid #f4f4f5;">Level</td>
                    <td style="padding:8px 0;border-top:1px solid #f4f4f5;"><strong style="color:{{ $color }};">{{ strtoupper($issue->level) }}</strong></td>
                </tr>
                <tr>
                    <td style="padding:8px 0;color:#71717a;border-top:1px solid #f4f4f5;">Environment</td>
                    <td style="padding:8px 0;border-top:1px solid #f4f4f5;">{{ $environment }}</td>
                </tr>
                <tr>
                    <td style="padding:8px 0;color:#71717a;border-top:1px solid #f4f4f5;">Events</td>
                    <td style="padding:8px 0;border-top:1px solid #f4f4f5;">{{ number_format($issue->events_count) }}</td>
                </tr>
                <tr>
                    <td style="padding:8px 0;color:#71717a;border-top:1px solid #f4f4f5;">First seen</td>
                    <td style="padding:8px 0;border-top:1px solid #f4f4f5;">{{ $issue->first_seen?->toDayDateTimeString() }} UTC</td>
                </tr>
                <tr>
                    <td style="padding:8px 0;color:#71717a;border-top:1px solid #f4f4f5;border-bottom:1px solid #f4f4f5;">Last seen</td>
                    <td style="padding:8px 0;border-top:1px solid #f4f4f5;border-bottom:1px solid #f4f4f5;">{{ $issue->last_seen?->toDayDateTimeString() }} UTC</td>
                </tr>
            </table>

            <a href="{{ $url }}" style="display:inline-block;padding:12px 20px;background:#18181b;color:#fff;text-decoration:none;border-radius:8px;font-weight:600;font-size:14px;">Open issue</a>

            @if($rule)
                <p style="margin:24px 0 0;font-size:12px;color:#a1a1aa;">Triggered by rule “{{ $rule->name }}” ({{ $rule->describe() }}). You will not receive another alert for this issue for {{ $rule->cooldown_minutes }} minutes.</p>
            @endif
        </td>
    </tr>
</table>
<p style="font-size:12px;color:#a1a1aa;margin:16px 0 0;">Sent by Loggerxo · <a href="{{ rtrim(config('app.url'), '/') }}" style="color:#a1a1aa;">{{ parse_url(config('app.url'), PHP_URL_HOST) }}</a></p>
</td></tr>
</table>
</body>
</html>
