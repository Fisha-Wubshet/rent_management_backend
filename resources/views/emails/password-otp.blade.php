<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>GizeBit password reset code</title>
</head>
<body style="margin:0;padding:24px;background:#f8fafc;font-family:Helvetica,Arial,sans-serif;color:#1e293b">
    <div style="max-width:480px;margin:0 auto;background:#ffffff;border-radius:16px;padding:32px;border:1px solid #e2e8f0">
        <div style="text-align:center;margin-bottom:24px">
            <div style="font-size:22px;font-weight:800;color:#4F46E5">GizeBit</div>
        </div>
        <h2 style="font-size:18px;font-weight:700;margin:0 0 12px">Hello {{ $firstName }},</h2>
        <p style="font-size:14px;line-height:1.6;color:#475569;margin:0 0 20px">
            You requested a password reset for your GizeBit account. Enter this code in the app to continue:
        </p>
        <div style="text-align:center;padding:20px;background:#EEF2FF;border-radius:12px;margin:16px 0">
            <div style="font-size:32px;font-weight:800;letter-spacing:8px;color:#4338CA">{{ $otp }}</div>
        </div>
        <p style="font-size:13px;color:#64748B;margin:16px 0 0">
            This code will expire in 10 minutes. If you did not request this reset, you can safely ignore this email.
        </p>
        <hr style="border:none;border-top:1px solid #e2e8f0;margin:24px 0">
        <p style="font-size:11px;color:#94a3b8;margin:0;text-align:center">
            GizeBit &middot; The complete rental management platform
        </p>
    </div>
</body>
</html>
