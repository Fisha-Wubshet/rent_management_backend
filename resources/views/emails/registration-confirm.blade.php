<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Confirm your GizeBit registration</title>
</head>
<body style="margin:0;padding:24px;background:#f8fafc;font-family:Helvetica,Arial,sans-serif;color:#1e293b">
    <div style="max-width:540px;margin:0 auto;background:#ffffff;border-radius:16px;padding:32px;border:1px solid #e2e8f0">
        <div style="text-align:center;margin-bottom:20px">
            <div style="font-size:24px;font-weight:800;color:#4F46E5">GizeBit</div>
        </div>

        <h1 style="font-size:22px;font-weight:800;margin:0 0 8px;color:#0F172A">Hi {{ $firstName }},</h1>
        <p style="font-size:14px;line-height:1.6;color:#475569;margin:0 0 16px">
            Thanks for registering <strong>{{ $shopName }}</strong> on GizeBit. Please confirm your email address so
            our team can review your request.
        </p>

        <div style="text-align:center;margin:28px 0 20px">
            <a href="{{ $confirmUrl }}" style="display:inline-block;padding:14px 32px;background:#4F46E5;color:#fff;text-decoration:none;border-radius:12px;font-weight:700;font-size:14.5px">
                Confirm my email
            </a>
        </div>

        <p style="font-size:12.5px;color:#64748B;margin:0 0 8px;line-height:1.55">
            Or copy and paste this link into your browser:
        </p>
        <p style="font-size:12px;color:#4F46E5;word-break:break-all;margin:0 0 24px;line-height:1.5">
            {{ $confirmUrl }}
        </p>

        <div style="padding:12px 14px;background:#F1F5F9;border-radius:10px;margin-bottom:20px">
            <p style="font-size:12.5px;color:#475569;margin:0;line-height:1.55">
                After you confirm, our team reviews new accounts within 24 hours. Once approved,
                we'll email you your login details.
            </p>
        </div>

        <hr style="border:none;border-top:1px solid #e2e8f0;margin:24px 0">
        <p style="font-size:11px;color:#94a3b8;margin:0;text-align:center">
            GizeBit &middot; The complete rental management platform
        </p>
    </div>
</body>
</html>
