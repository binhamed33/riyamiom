<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ترويساتُ الأمان على **كلّ** ردّ — لا على ما وجد مساراً.
 *
 * ═══ ما كشفه فاحصُ ZAP ═══
 *
 * صفحةُ 404 كانت تخرج بلا سياسة أمان ولا X-Frame-Options ولا HSTS.
 * والدليلُ في جسمها: سكربتُ Cloudflare يُحقَن بـnonce حين يجد
 * السياسةَ في الترويسات، وفي صفحة 404 يُحقَن بلا nonce — أي أنّ
 * الترويسةَ لم تكن هناك.
 *
 * والسببُ أنّ هذا الوسيطَ كان في مجموعة web، ومجموعةُ web لا تعمل إلا
 * بعد أن يجد الموجِّهُ مساراً. فمسارٌ غيرُ موجود يُرمى قبلها، ويخرج
 * ردُّه عارياً. فصار وسيطاً عامّاً يلتفّ على كلّ ردّ.
 *
 * ═══ وما عُدِّل في السياسة ═══
 *
 *   • ‎https: http:‎ من script-src: مع strict-dynamic تتجاهلهما
 *     المتصفّحاتُ الحديثةُ كلُّها، وZAP يعدّهما بابَين مفتوحَين. وما كان
 *     يُحمَّل من شبكاتٍ خارجيّة صار محمولاً مع النظام.
 *   • ‎object-src 'none'‎: بلا تعريفٍ يسقط إلى default-src، وZAP يطلبه
 *     صريحاً — ولا Flash ولا Java هنا.
 *   • ‎X-XSS-Protection: 0‎ لا ‎1; mode=block‎: المرشّحُ القديم أُزيل من
 *     المتصفّحات لأنّه صار هو نفسُه أداةَ تسريب، والقيمةُ الموصى بها
 *     اليوم صفر.
 *   • و‎X-Powered-By: PHP/8.4‎ تُمحى: PHP يضيفها بنفسه خارج الردّ،
 *     وتقول للفاحص أيَّ إصدارٍ يهاجم.
 *
 * ═══ وما بقي عمداً ═══
 *
 *   • ‎'unsafe-eval'‎: Alpine يقيّم تعابيرَه بـnew Function. البديلُ
 *     ‎@alpinejs/csp‎ ويكسر نصفَ القوالب.
 *   • ‎style-src 'unsafe-inline'‎: Tailwind من CDN يحقن أنماطَه، ومئاتُ
 *     ‎style=""‎ في القوالب. زوالُه يحتاج بناءَ Tailwind لا تحميلَه —
 *     وهو عملٌ مستقلّ.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));
        view()->share('cspNonce', $nonce);

        $response = $next($request);

        // ‏PHP يضيف X-Powered-By على مستوى الخادم لا الردّ، فلا يُزال من
        // $response->headers — يُزال من طبقة PHP نفسِها قبل الإرسال
        if (!headers_sent()) {
            header_remove('X-Powered-By');
        }

        // SAMEORIGIN لا DENY: عارض المستندات يعرض PDF داخل إطار من
        // الموقع نفسه. DENY كانت تحجب الموقع عن نفسه فتظهر المعاينة
        // صندوقاً فارغاً — والحماية من مواقع خارجية تبقى كاملة.
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '0');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        /*
         * ولا يُفهرَس شيءٌ من نظام المكتب.
         *
         * ‏robots.txt طلبٌ يُطاع أو لا يُطاع، ولا يمنع فهرسةَ صفحةٍ
         * وصلها الزاحفُ من رابطٍ خارجيّ. والترويسةُ أمرٌ لا طلب:
         * صفحةٌ تحملها لا تدخل الفهرس ولو زُحف إليها.
         */
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        $csp = [
            "default-src 'self'",
            "script-src 'nonce-{$nonce}' 'strict-dynamic' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self' data: blob: https://static.cloudflareinsights.com",
            // ‏cloudflareinsights.com (بلا static) هو ما يبعث إليه المنارُ قياساتِه
            "connect-src 'self' https://cdn.tailwindcss.com https://static.cloudflareinsights.com https://cloudflareinsights.com",
            "object-src 'none'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            'upgrade-insecure-requests',
        ];
        // ═══ سياسةٌ وضعها المتحكّمُ لا تُستبدل ═══
        //
        // Attachments::respond تُقدّم المرفقَ بـ"default-src 'none'; sandbox"
        // — عزلٌ مقصودٌ لملفٍّ رفعه غيرُ صاحب الصفحة. وهذا الوسيطُ يعمل
        // بعد المتحكّم، فكان set() يستبدل ذلك العزلَ بسياسة التطبيق
        // السخيّة في كلّ مرفق. والحارسُ نفسُه يستعمله لارافل في
        // ServeFile: ما وُضع لا يُداس.
        if (!$response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', implode('; ', $csp));
        }

        if (config('app.env') === 'production') {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }
}
