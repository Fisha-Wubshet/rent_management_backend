<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>About your GizeBit registration</title>
</head>
<body style="margin:0;padding:24px;background:#f8fafc;font-family:Helvetica,Arial,sans-serif;color:#1e293b">
    <div style="max-width:540px;margin:0 auto;background:#ffffff;border-radius:16px;padding:32px;border:1px solid #e2e8f0">
        <div style="text-align:center;margin-bottom:20px">
            <div style="font-size:24px;font-weight:800;color:#4F46E5">GizeBit</div>
        </div>

        <h1 style="font-size:22px;font-weight:800;margin:0 0 8px;color:#0F172A">Hi {{ $firstName }},</h1>
        <p style="font-size:14px;line-height:1.6;color:#475569;margin:0 0 16px">
            Thank you for your interest in GizeBit for <strong>{{ $shopName }}</strong>.
        </p>

        <p style="font-size:14px;line-height:1.6;color:#475569;margin:0 0 20px">
            Unfortunately, we're unable to approve your registration at this time.
        </p>

        <div style="padding:14px 16px;background:#F8FAFC;border-left:3px solid #94A3B8;border-radius:6px;margin-bottom:20px">
            <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:0.06em;color:#64748B;margin-bottom:6px">
                Reason
            </div>
            <p style="font-size:13.5px;color:#334155;margin:0;line-height:1.5">{{ $reasonLabel }}</p>
            @if($note)
                <p style="font-size:12.5px;color:#64748B;margin:8px 0 0;line-height:1.5">{{ $note }}</p>
            @endif
        </div>

        <p style="font-size:13px;color:#64748B;margin:0 0 20px;line-height:1.55">
            If you believe this was a mistake or would like to reapply with updated information,
            please reply to this email.
        </p>

        <hr style="border:none;border-top:1px solid #e2e8f0;margin:24px 0">
        <p style="font-size:11px;color:#94a3b8;margin:0;text-align:center">
            GizeBit &middot; The complete rental management platform
        </p>
    </div>
</body>
</html>
