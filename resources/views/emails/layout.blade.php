{{--
    ترويسةُ كل بريدٍ يخرج من النظام.

    البريد يُقرأ في عشرات العملاء لكلٍّ محرّكُه: لا Flex ولا Grid ولا
    ملفّ أنماطٍ خارجي — جداولُ وأنماطٌ في السطر، وهي الصيغة الوحيدة
    التي تصمد في Outlook وGmail معاً.

    ولا يُذكر في شيء منها اسمُ مطوّرٍ ولا اسمُ شخص: الموكّل يرى مكتبه
    ويرى «مُداوَلة» ولا يرى من بناها.
--}}
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>{{ $subject ?? $officeName }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f2ee;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f2ee;padding:24px 12px;">
<tr><td align="center">

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="max-width:600px;background:#ffffff;border:1px solid #e7e2d8;border-radius:14px;overflow:hidden;">

        {{-- الترويسة: شريطٌ نحاسيّ واسمُ المكتب --}}
        <tr>
            <td style="background:#1b1c20;padding:22px 28px;border-bottom:3px solid #9c7734;">
                <p style="margin:0;font-family:'Segoe UI',Tahoma,Arial,sans-serif;font-size:17px;font-weight:700;color:#ffffff;line-height:1.5;">
                    {{ $officeName }}
                </p>
                @isset($heading)
                    <p style="margin:6px 0 0;font-family:'Segoe UI',Tahoma,Arial,sans-serif;font-size:12px;color:#c9a961;line-height:1.6;">
                        {{ $heading }}
                    </p>
                @endisset
            </td>
        </tr>

        {{-- المتن --}}
        <tr>
            <td style="padding:28px;font-family:'Segoe UI',Tahoma,Arial,sans-serif;font-size:14px;line-height:2;color:#2b2b2f;">
                @if(!empty($clientName))
                    <p style="margin:0 0 16px;font-size:15px;font-weight:600;color:#1b1c20;">الفاضل/ة {{ $clientName }}</p>
                @endif

                @yield('body')

                @if(!empty($portalUrl))
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:24px 0 4px;">
                        <tr>
                            <td style="background:#9c7734;border-radius:10px;">
                                <a href="{{ $portalUrl }}"
                                   style="display:inline-block;padding:12px 26px;font-family:'Segoe UI',Tahoma,Arial,sans-serif;font-size:14px;font-weight:700;color:#ffffff;text-decoration:none;">
                                    الدخول إلى بوابة المتابعة
                                </a>
                            </td>
                        </tr>
                    </table>
                    <p style="margin:10px 0 0;font-size:11px;color:#8a8578;line-height:1.8;">
                        إن لم يعمل الزرّ، انسخ هذا الرابط إلى المتصفّح:<br>
                        <span style="color:#6f6a5e;word-break:break-all;">{{ $portalUrl }}</span>
                    </p>
                @endif
            </td>
        </tr>

        {{-- الذيل --}}
        <tr>
            <td style="background:#faf8f4;border-top:1px solid #e7e2d8;padding:18px 28px;">
                <p style="margin:0;font-family:'Segoe UI',Tahoma,Arial,sans-serif;font-size:11px;color:#8a8578;line-height:1.9;">
                    هذه رسالة آلية من نظام <strong style="color:#9c7734;">مُداوَلة</strong> لإدارة المكاتب القانونية.
                    @if(!empty($replyTo))
                        <br>للردّ أو الاستفسار، راسل مكتبك على: <span style="color:#6f6a5e;">{{ $replyTo }}</span>
                    @endif
                    <br>إن وصلتك هذه الرسالة بالخطأ فتجاهلها، ولا تُشارك رابط البوابة مع أحد.
                </p>
            </td>
        </tr>
    </table>

</td></tr>
</table>
</body>
</html>
