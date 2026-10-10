<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Invitation</title></head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#18181b;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:32px 16px;"><tr><td align="center">
<table role="presentation" width="560" cellspacing="0" cellpadding="0" style="max-width:560px;width:100%;background:#fff;border-radius:12px;border:1px solid #e4e4e7;overflow:hidden;">
    <tr><td style="padding:20px 28px;background:#09090b;color:#fafafa;font-size:16px;font-weight:600;">Loggerxo</td></tr>
    <tr><td style="padding:28px;font-size:15px;line-height:1.6;">
        <p style="margin:0 0 12px;">{{ $inviter?->name ?? 'A teammate' }} invited you to join <strong>{{ $organization->name }}</strong> on Loggerxo as {{ $invitation->role === 'admin' ? 'an admin' : 'a member' }}.</p>
        <p style="margin:0 0 24px;">Loggerxo collects errors, logs and performance data from your applications in one place.</p>
        <a href="{{ $url }}" style="display:inline-block;padding:12px 20px;background:#18181b;color:#fff;text-decoration:none;border-radius:8px;font-weight:600;font-size:14px;">Accept invitation</a>
        <p style="margin:24px 0 0;font-size:12px;color:#71717a;">This link expires {{ $invitation->expires_at->toDayDateTimeString() }} UTC. If the button does not work, open:<br><a href="{{ $url }}" style="color:#71717a;word-break:break-all;">{{ $url }}</a></p>
    </td></tr>
</table>
</td></tr></table>
</body>
</html>
