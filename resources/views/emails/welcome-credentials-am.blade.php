<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>ወደ GizeBit እንኳን ደህና መጡ</title>
</head>
<body style="margin:0;padding:24px;background:#f8fafc;font-family:'Noto Sans Ethiopic',Helvetica,Arial,sans-serif;color:#1e293b">
    <div style="max-width:540px;margin:0 auto;background:#ffffff;border-radius:16px;padding:32px;border:1px solid #e2e8f0">
        <div style="text-align:center;margin-bottom:20px">
            <div style="font-size:24px;font-weight:800;color:#4F46E5">GizeBit</div>
        </div>

        @if($isReset)
        <h1 style="font-size:22px;font-weight:800;margin:0 0 8px;color:#0F172A">ሰላም {{ $firstName }}፣</h1>
        <p style="font-size:14px;line-height:1.6;color:#475569;margin:0 0 20px">
            የGizeBit ፓስዎርድዎ ተስተካክሏል። <strong>አዲሱ ፓስዎርድዎ ከታች ተመልክቷል።</strong> ከገቡ በኋላ በማንኛውም ጊዜ መቀየር ይችላሉ።
        </p>
        @else
        <h1 style="font-size:22px;font-weight:800;margin:0 0 8px;color:#0F172A">እንኳን ደህና መጡ, {{ $firstName }} 👋</h1>
        <p style="font-size:14px;line-height:1.6;color:#475569;margin:0 0 20px">
            የGizeBit መለያዎ ተፈጥሯል። ከታች ያሉት የመግቢያ መረጃዎችዎ ናቸው — ይህን ኢሜይል በደህና ቦታ ያስቀምጡ እና በተቻለ ፍጥነት ይግቡ።
        </p>
        @endif

        <!-- Login credentials -->
        <div style="margin:20px 0 8px;font-size:11px;font-weight:800;letter-spacing:0.08em;color:#64748B;text-transform:uppercase">
            የመግቢያ መረጃዎ
        </div>
        <div style="padding:18px 20px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:12px;margin-bottom:16px">
            <div style="margin-bottom:12px">
                <div style="font-size:11px;color:#64748B;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:2px">ስልክ ቁጥር</div>
                <div style="font-family:'Courier New',monospace;font-size:15px;font-weight:700;color:#0F172A;letter-spacing:0.5px">{{ $phone }}</div>
            </div>
            <div>
                <div style="font-size:11px;color:#64748B;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:2px">ፓስዎርድ</div>
                <div style="font-family:'Courier New',monospace;font-size:15px;font-weight:700;color:#0F172A;letter-spacing:0.5px">{{ $password }}</div>
            </div>
        </div>

        <div style="padding:12px 14px;background:#FEF3C7;border-radius:10px;border-left:3px solid #F59E0B;margin-bottom:20px">
            <p style="font-size:12.5px;line-height:1.55;color:#92400E;margin:0">
                @if($isReset)
                    <strong>ይህ ፓስዎርድ ጊዜያዊ ነው።</strong> ከገቡ በኋላ ወዲያውኑ አዲስ ፓስዎርድ እንዲያዘጋጁ ይጠየቃሉ።
                @else
                    <strong>በመጀመሪያ ግቢያ ፓስዎርድዎን ይቀይሩ።</strong> ይህ ፓስዎርድ አንድ ጊዜ ብቻ ይሠራል — ከገቡ በኋላ ወዲያውኑ አዲስ ፓስዎርድ እንዲያዘጋጁ ይጠየቃሉ።
                @endif
            </p>
        </div>

        @if($recoveryCode)
        <!-- Recovery code -->
        <div style="margin:20px 0 8px;font-size:11px;font-weight:800;letter-spacing:0.08em;color:#64748B;text-transform:uppercase">
            የመልሶ ማግኛ ኮድዎ
        </div>
        <div style="padding:18px 20px;background:#F5F3FF;border:2px dashed #C4B5FD;border-radius:12px;text-align:center;margin-bottom:12px">
            <div style="font-family:'Courier New',monospace;font-size:20px;font-weight:800;letter-spacing:4px;color:#4338CA">
                {{ $recoveryCode }}
            </div>
        </div>
        <p style="font-size:12.5px;color:#64748B;margin:0 0 20px;line-height:1.55">
            የመልሶ ማግኛ ኮድ ፓስዎርድዎን ከረሱ ዳግም ለማስተካከል የሚያገለግል <strong>የመጠባበቂያ ቁልፍ</strong> ነው።
            በደህና ቦታ ያስቀምጡ — በወረቀት፣ በፓስዎርድ ማኔጀር፣ ወይም እርስዎ ብቻ በሚደርሱበት ቦታ።
            @if($hasEmail)
            ኮዱን ካጡ በዚህ ኢሜይል በኩል ደግሞ ፓስዎርድዎን ማስተካከል ይችላሉ።
            @endif
        </p>
        @endif

        <div style="text-align:center;margin:24px 0 8px">
            <a href="{{ $loginUrl }}" style="display:inline-block;padding:12px 28px;background:#4F46E5;color:#fff;text-decoration:none;border-radius:10px;font-weight:700;font-size:14px">
                ወደ GizeBit ይግቡ
            </a>
        </div>

        <hr style="border:none;border-top:1px solid #e2e8f0;margin:24px 0">
        <p style="font-size:11px;color:#94a3b8;margin:0;text-align:center">
            GizeBit &middot; ለኪራይ ንግድዎ የተሟላ ስርዓት
        </p>
    </div>
</body>
</html>
