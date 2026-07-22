<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Welcome to GizeBit</title>
</head>
<body style="margin:0;padding:24px;background:#f8fafc;font-family:Helvetica,Arial,sans-serif;color:#1e293b">
    <div style="max-width:540px;margin:0 auto;background:#ffffff;border-radius:16px;padding:32px;border:1px solid #e2e8f0">
        <div style="text-align:center;margin-bottom:20px">
            <div style="font-size:24px;font-weight:800;color:#4F46E5">GizeBit</div>
        </div>

        @if($isReset)
        <h1 style="font-size:22px;font-weight:800;margin:0 0 8px;color:#0F172A">Hi {{ $firstName }},</h1>
        <p style="font-size:14px;line-height:1.6;color:#475569;margin:0 0 20px">
            Your GizeBit password has been reset. <strong>Your new password is shown below.</strong> You can change it any time after you log in.
        </p>
        @else
        <h1 style="font-size:22px;font-weight:800;margin:0 0 8px;color:#0F172A">Welcome, {{ $firstName }} 👋</h1>
        <p style="font-size:14px;line-height:1.6;color:#475569;margin:0 0 20px">
            Your GizeBit account has been created. Below are your login credentials — please save this email somewhere safe and sign in as soon as possible.
        </p>
        @endif

        <!-- Login credentials -->
        <div style="margin:20px 0 8px;font-size:11px;font-weight:800;letter-spacing:0.08em;color:#64748B;text-transform:uppercase">
            Your login details
        </div>
        <div style="padding:18px 20px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:12px;margin-bottom:16px">
            <div style="margin-bottom:12px">
                <div style="font-size:11px;color:#64748B;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:2px">Phone number</div>
                <div style="font-family:'Courier New',monospace;font-size:15px;font-weight:700;color:#0F172A;letter-spacing:0.5px">{{ $phone }}</div>
            </div>
            <div>
                <div style="font-size:11px;color:#64748B;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:2px">Password</div>
                <div style="font-family:'Courier New',monospace;font-size:15px;font-weight:700;color:#0F172A;letter-spacing:0.5px">{{ $password }}</div>
            </div>
        </div>

        <div style="padding:12px 14px;background:#FEF3C7;border-radius:10px;border-left:3px solid #F59E0B;margin-bottom:20px">
            <p style="font-size:12.5px;line-height:1.55;color:#92400E;margin:0">
                @if($isReset)
                    <strong>This password is temporary.</strong> You'll be asked to choose a new one right after you sign in.
                @else
                    <strong>Change your password on first login.</strong> This password will only work once — you'll be asked to set a new one right after you sign in.
                @endif
            </p>
        </div>

        @if($recoveryCode)
        <!-- Recovery code -->
        <div style="margin:20px 0 8px;font-size:11px;font-weight:800;letter-spacing:0.08em;color:#64748B;text-transform:uppercase">
            Your recovery code
        </div>
        <div style="padding:18px 20px;background:#F5F3FF;border:2px dashed #C4B5FD;border-radius:12px;text-align:center;margin-bottom:12px">
            <div style="font-family:'Courier New',monospace;font-size:20px;font-weight:800;letter-spacing:4px;color:#4338CA">
                {{ $recoveryCode }}
            </div>
        </div>
        <p style="font-size:12.5px;color:#64748B;margin:0 0 20px;line-height:1.55">
            A recovery code is your <strong>backup key</strong> to reset your password if you ever forget it.
            Save it somewhere safe — a paper note, a password manager, or somewhere only you can reach.
            @if($hasEmail)
            You can also reset your password via this email if you lose the code.
            @endif
        </p>
        @endif

        <div style="text-align:center;margin:24px 0 8px">
            <a href="{{ $loginUrl }}" style="display:inline-block;padding:12px 28px;background:#4F46E5;color:#fff;text-decoration:none;border-radius:10px;font-weight:700;font-size:14px">
                Sign in to GizeBit
            </a>
        </div>

        <hr style="border:none;border-top:1px solid #e2e8f0;margin:24px 0">
        <p style="font-size:11px;color:#94a3b8;margin:0;text-align:center">
            GizeBit &middot; The complete rental management platform
        </p>
    </div>
</body>
</html>
