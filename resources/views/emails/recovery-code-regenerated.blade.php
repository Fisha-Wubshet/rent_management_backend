<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>New recovery code</title>
</head>
<body style="margin:0;padding:24px;background:#f8fafc;font-family:Helvetica,Arial,sans-serif;color:#1e293b">
    <div style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:16px;padding:32px;border:1px solid #e2e8f0">
        <div style="text-align:center;margin-bottom:20px">
            <div style="font-size:22px;font-weight:800;color:#4F46E5">GizeBit</div>
        </div>

        <div style="display:flex;align-items:center;gap:8px;padding:12px 14px;background:#FEF3C7;border-radius:10px;margin-bottom:20px">
            <span style="font-size:18px;line-height:1">⚠️</span>
            <span style="font-size:13px;font-weight:700;color:#92400E">Security notification</span>
        </div>

        <h2 style="font-size:18px;font-weight:700;margin:0 0 12px">Hello {{ $firstName }},</h2>
        <p style="font-size:14px;line-height:1.6;color:#475569;margin:0 0 16px">
            A new GizeBit recovery code was generated on <strong>{{ $when }}</strong>. Your previous code no longer works.
        </p>

        <div style="margin:20px 0 8px;font-size:11px;font-weight:800;letter-spacing:0.08em;color:#64748B;text-transform:uppercase">
            Your new recovery code
        </div>
        <div style="padding:18px 20px;background:#F5F3FF;border:2px dashed #C4B5FD;border-radius:12px;text-align:center;margin-bottom:16px">
            <div style="font-family:'Courier New',Courier,monospace;font-size:22px;font-weight:800;letter-spacing:4px;color:#4338CA">
                {{ $code }}
            </div>
        </div>
        <p style="font-size:12.5px;color:#64748B;margin:0 0 20px;line-height:1.55;text-align:center">
            Save this somewhere safe — a paper note, password manager, or somewhere only you can reach.
            It's the only way to reset your password if you forget it.
        </p>

        <div style="padding:14px 16px;background:#FEF2F2;border-radius:10px;border-left:3px solid #DC2626">
            <p style="font-size:13px;line-height:1.55;color:#991B1B;margin:0">
                <strong>If this wasn't you</strong>, someone may have access to your account.
                Sign in immediately, change your password, and contact your shop admin.
            </p>
        </div>

        <hr style="border:none;border-top:1px solid #e2e8f0;margin:24px 0">
        <p style="font-size:11px;color:#94a3b8;margin:0;text-align:center">
            GizeBit &middot; The complete rental management platform
        </p>
    </div>
</body>
</html>
