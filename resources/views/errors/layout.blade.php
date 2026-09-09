{{-- صفحاتُ الأخطاء — بلغة المكتب، وبلا اسمِ إطارٍ ولا شبكةٍ خارجيّة.

     ═══ ما كان ═══

     صفحةُ 404 كانت صفحةَ لارافل الافتراضيّة: إنجليزيّةٌ في نظامٍ عربيّ،
     تقول للفاحص أيَّ إطارٍ يهاجم، وتحقن فيها Cloudflare سكربتَها بلا
     nonce لأنّ الردَّ خرج بلا سياسة أمان.

     ═══ وما هنا ═══

     لا Tailwind ولا خطٌّ من الشبكة ولا سكربت: صفحةُ خطأٍ تُعرض حين يكون
     شيءٌ معطوباً، فلا تتّكئ على شيءٍ قد يكون هو المعطوب. أنماطُها في
     داخلها، وخطُّها خطُّ النظام. --}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') · مُداوَلة</title>
    <style>
        :root { --bg: #F6F3EC; --card: #FFFFFF; --ink: #121826; --muted: #6B7280; --gold: #C9A227; --gold-ink: #8C6A12; --line: rgba(201,162,39,.25); }
        @media (prefers-color-scheme: dark) {
            :root { --bg: #0D111B; --card: #121826; --ink: #F5F1E8; --muted: #94A3B8; --gold-ink: #E5C158; --line: rgba(229,193,88,.22); }
        }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem;
               background: var(--bg); color: var(--ink); font-family: "IBM Plex Sans Arabic", "Segoe UI", Tahoma, system-ui, sans-serif; }
        .card { width: 100%; max-width: 30rem; background: var(--card); border: 1px solid var(--line); border-radius: 1rem; padding: 2.5rem 2rem; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,.06); }
        .code { font-size: .8rem; letter-spacing: .3em; color: var(--gold-ink); font-weight: 700; margin-bottom: .75rem; }
        h1 { font-size: 1.45rem; margin: 0 0 .75rem; font-weight: 700; }
        p { color: var(--muted); line-height: 1.9; margin: 0 0 1.5rem; font-size: .95rem; }
        a.btn { display: inline-block; padding: .65rem 1.4rem; border-radius: .6rem; background: var(--gold); color: #1a1405; font-weight: 700; text-decoration: none; }
        a.btn:hover { filter: brightness(1.05); }
        .brand { margin-top: 1.75rem; font-size: .75rem; color: var(--muted); }
    </style>
</head>
<body>
    <main class="card" role="main">
        <div class="code">@yield('code')</div>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <a class="btn" href="@yield('action-href', url('/'))">@yield('action', 'العودة إلى الرئيسة')</a>
        <div class="brand">مُداوَلة — نظام إدارة مكاتب المحاماة</div>
    </main>
</body>
</html>
