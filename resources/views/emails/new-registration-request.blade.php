<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>New GizeBit registration</title>
</head>
<body style="margin:0;padding:24px;background:#f8fafc;font-family:Helvetica,Arial,sans-serif;color:#1e293b">
    <div style="max-width:540px;margin:0 auto;background:#ffffff;border-radius:16px;padding:32px;border:1px solid #e2e8f0">
        <div style="text-align:center;margin-bottom:16px">
            <div style="font-size:24px;font-weight:800;color:#4F46E5">GizeBit</div>
        </div>

        <h1 style="font-size:20px;font-weight:800;margin:0 0 6px;color:#0F172A">New registration request</h1>
        <p style="font-size:13px;color:#64748B;margin:0 0 22px">A new shop is waiting for your review.</p>

        <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:12px;padding:16px 18px;margin-bottom:20px">
            <table style="width:100%;font-size:13.5px;color:#0F172A">
                <tr>
                    <td style="padding:6px 0;color:#64748B;width:40%">Shop</td>
                    <td style="padding:6px 0;font-weight:600">{{ $reg->shop_name }}</td>
                </tr>
                <tr>
                    <td style="padding:6px 0;color:#64748B">Admin</td>
                    <td style="padding:6px 0">{{ $reg->first_name }} {{ $reg->last_name }}</td>
                </tr>
                <tr>
                    <td style="padding:6px 0;color:#64748B">Phone</td>
                    <td style="padding:6px 0;font-family:'Courier New',monospace">{{ $reg->phone_number }}</td>
                </tr>
                @if($reg->email)
                    <tr>
                        <td style="padding:6px 0;color:#64748B">Email</td>
                        <td style="padding:6px 0">{{ $reg->email }}</td>
                    </tr>
                @endif
                @if($reg->city)
                    <tr>
                        <td style="padding:6px 0;color:#64748B">City</td>
                        <td style="padding:6px 0">{{ $reg->city }}</td>
                    </tr>
                @endif
                @if($reg->item_label)
                    <tr>
                        <td style="padding:6px 0;color:#64748B">Rents out</td>
                        <td style="padding:6px 0">{{ $reg->item_label }}</td>
                    </tr>
                @endif
            </table>
        </div>

        <div style="text-align:center;margin:12px 0 8px">
            <a href="{{ $reviewUrl }}" style="display:inline-block;padding:12px 26px;background:#4F46E5;color:#fff;text-decoration:none;border-radius:10px;font-weight:700;font-size:14px">
                Review in dashboard
            </a>
        </div>

        <hr style="border:none;border-top:1px solid #e2e8f0;margin:24px 0">
        <p style="font-size:11px;color:#94a3b8;margin:0;text-align:center">
            GizeBit &middot; Super-admin notification
        </p>
    </div>
</body>
</html>
