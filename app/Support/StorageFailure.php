<?php

namespace App\Support;

use Illuminate\Http\Response;

/**
 * قرصٌ لا يُكتب عليه — صفحةٌ تقول ذلك بدل صفحةٍ بيضاء أو حلقةِ تحويل.
 *
 * ═══ ما وقع ═══
 *
 * امتلأ قرصُ الخادم. فكلُّ صفحةٍ يحتاج قالبُها إلى تجميعٍ تسقط في
 * file_put_contents «errno=28 No space left on device» — ومعالجُ
 * الاستثناءات يعالج الخطأَ بتحويل الزائر إلى لوحة التحكّم برسالة، ولوحةُ
 * التحكّم قالبٌ لا يُجمَّع كذلك. فمن دخل رأى «تعذر تحميل لوحة التحكم»
 * بلا سبب، ومن لم يدخل دار بين /login و/dashboard حتى قال متصفّحُه
 * «too many redirects». وقبل ذلك كلِّه كان السجلُّ نفسُه لا يُكتب،
 * فخرجت الصفحةُ بيضاءَ بلا حرف.
 *
 * ═══ وما هنا ═══
 *
 * خطأُ كتابةٍ على القرص يُعرَف من رسالته — لا من موضعه: هو نفسُه في
 * تجميع القوالب وفي ملفّ الجلسة وفي ذاكرة الملفّات وفي السجلّ. فيُردّ
 * عليه بصفحةٍ **لا تحتاج إلى قرص**: نصٌّ جاهز، بلا قالبٍ ولا جلسةٍ ولا
 * تحويل. تقول للمستخدم ما وقع بكلماته، وللدعم أين ينظر — بلا مسارٍ
 * يكشف بنيةَ الخادم.
 */
class StorageFailure
{
    /**
     * رسائلُ PHP حين يخيب القرص — بأرقام errno كما يكتبها.
     *
     * ولا «Failed to open stream» وحدَها: هي عبارةُ PHP لكلّ فتحٍ خائب،
     * ومنها قراءةُ ملفٍّ غيرِ موجود — فكانت ستُلبس عطبَ كودٍ ثوبَ قرصٍ
     * ممتلئ. فتُقبل «Permission denied» حين يكون الهدفُ داخل storage/
     * وحدَه: مجلّدٌ بملك root أنشأه أمرٌ شُغّل بـsudo.
     */
    private const SIGNS = [
        'No space left on device',
        'errno=28',
        'Read-only file system',
        'errno=30',
        'Disk quota exceeded',
        'errno=122',
        'could not be opened in append mode',
        'Writing to the log file failed',
    ];

    /** أهذا — أو ما تحته من أسباب — خيبةُ كتابةٍ على القرص؟ */
    public static function of(\Throwable $e): bool
    {
        for ($depth = 0; $e !== null && $depth < 8; $e = $e->getPrevious(), $depth++) {
            $message = $e->getMessage();

            foreach (self::SIGNS as $sign) {
                if (str_contains($message, $sign)) {
                    return true;
                }
            }

            if (str_contains($message, 'Permission denied') && str_contains($message, '/storage/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * الردُّ الجاهز: ‎500‎ بجسمٍ عربيٍّ لا يعتمد على قالبٍ ولا جلسة.
     *
     * ‏no-store: Cloudflare أو متصفّحٌ يحفظ صفحةَ خطأٍ يُبقيها بعد الإصلاح.
     */
    public static function response(): Response
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>خادمُ المكتب لا يستطيع الحفظ · مُداوَلة</title>
<style>
:root{--bg:#F6F3EC;--card:#FFFFFF;--ink:#121826;--muted:#6B7280;--gold-ink:#8C6A12;--line:rgba(201,162,39,.25)}
@media (prefers-color-scheme:dark){:root{--bg:#0D111B;--card:#121826;--ink:#F5F1E8;--muted:#94A3B8;--gold-ink:#E5C158;--line:rgba(229,193,88,.22)}}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.5rem;background:var(--bg);color:var(--ink);font-family:"IBM Plex Sans Arabic","Segoe UI",Tahoma,system-ui,sans-serif}
.card{width:100%;max-width:30rem;background:var(--card);border:1px solid var(--line);border-radius:1rem;padding:2.5rem 2rem;text-align:center}
.code{font-size:.8rem;letter-spacing:.3em;color:var(--gold-ink);font-weight:700;margin-bottom:.75rem}
h1{font-size:1.35rem;margin:0 0 .75rem}
p{color:var(--muted);line-height:1.9;margin:0 0 .5rem}
code{direction:ltr;display:inline-block;font-size:.85em;color:var(--gold-ink)}
</style>
</head>
<body>
<div class="card">
<div class="code">500</div>
<h1>خادمُ المكتب لا يستطيع الحفظ الآن</h1>
<p>امتلأ قرصُ الخادم أو أُغلقت صلاحيّةُ الكتابة على مجلّد التخزين، فلا تُحفظ صفحةٌ ولا سجلّ حتى يُعالَج.</p>
<p>لم تُفقد بياناتُك — ما حُفظ من قبلُ في مكانه. أبلغ الدعم بهذه الرسالة وبوقت ظهورها.</p>
<p><code>storage: no space / not writable</code></p>
</div>
</body>
</html>
HTML;

        return new Response($html, 500, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
