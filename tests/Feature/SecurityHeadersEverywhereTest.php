<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ترويساتُ الأمان على كلّ ردّ — وما كشفه فاحصُ ZAP على المكتب الحيّ.
 *
 * ═══ الدليلُ الذي جاء من الفاحص ═══
 *
 * صفحةُ 404 خرجت بلا سياسة أمان: سكربتُ Cloudflare يُحقَن فيها بلا
 * nonce، بينما يُحقَن في صفحات التحويل بـnonce — لأنّ Cloudflare يقرأ
 * الملحَ من ترويسة السياسة إن وجدها. وصفحةُ 404 نفسُها كانت صفحةَ لارافل
 * الإنجليزيّةَ الافتراضيّة.
 *
 * والسببُ: الوسيطُ كان في مجموعة web، ومجموعةُ web لا تعمل إلا بعد أن
 * يجد الموجِّهُ مساراً. فمسارٌ غيرُ موجودٍ يُرمى قبلها.
 */
class SecurityHeadersEverywhereTest extends TestCase
{
    use RefreshDatabase;

    /** ═══ ما كان عارياً: 404 على مسارٍ لا وجود له ═══ */
    public function test_a_missing_route_still_carries_every_security_header(): void
    {
        $r = $this->get('/this-route-does-not-exist-' . bin2hex(random_bytes(4)));

        $r->assertNotFound();
        $r->assertHeader('Content-Security-Policy');
        $r->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $r->assertHeader('X-Content-Type-Options', 'nosniff');
        $r->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $r->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $r->assertHeader('Permissions-Policy');
    }

    /** وصفحةُ 404 عربيّةٌ من عندنا، لا صفحةُ الإطار. */
    public function test_the_404_page_is_ours_and_in_arabic(): void
    {
        $html = $this->get('/no-such-page-' . bin2hex(random_bytes(4)))->assertNotFound()->getContent();

        $this->assertStringContainsString('الصفحة غير موجودة', $html);
        $this->assertStringContainsString('lang="ar" dir="rtl"', $html);

        // لا اسمَ إطارٍ ولا نصَّه الافتراضيّ ولا شبكةً خارجيّة تتّكئ عليها
        $this->assertStringNotContainsString('Not Found', $html, 'صفحةُ لارافل الافتراضيّة ما زالت تُعرض');
        $this->assertStringNotContainsString('normalize.css', $html);
        $this->assertStringNotContainsString('https://cdn.', $html, 'صفحةُ خطأٍ تتّكئ على شبكةٍ خارجيّة');
        $this->assertStringNotContainsString('<script', $html, 'صفحةُ خطأٍ فيها سكربت — لا حاجةَ ولا مبرّر');
    }

    /** وكلُّ رمزٍ له صفحتُه: 401 · 403 · 404 · 419 · 429 · 500 · 503. */
    public function test_every_http_error_has_an_arabic_page(): void
    {
        foreach ([401, 403, 404, 419, 429, 500, 503] as $code) {
            $this->assertFileExists(resource_path("views/errors/{$code}.blade.php"), "لا صفحةَ للرمز {$code}");
        }

        // و503 هي ما يعرضه artisan down — فوضعُ الصيانة أثناء النشر
        // لا يُظهر «Service Unavailable» الإنجليزيّة
        $html = view('errors.503')->render();
        $this->assertStringContainsString('صيانة', $html);
    }

    /**
     * ═══ السياسةُ نفسُها بعد التشديد ═══
     *
     * ‏https: http: في script-src بابان يعدّهما الفاحصُ مفتوحَين، وتتجاهلهما
     * المتصفّحاتُ الحديثةُ مع strict-dynamic أصلاً. و‎object-src‎ بلا تعريفٍ
     * يُعدّ ناقصاً. و‎X-XSS-Protection: 1‎ صار المرشّحُ نفسُه أداةَ تسريب.
     */
    public function test_the_csp_has_no_scheme_wildcards_and_defines_object_src(): void
    {
        $csp = (string) $this->get('/login')->assertOk()->headers->get('Content-Security-Policy');

        preg_match("/script-src ([^;]+)/", $csp, $m);
        $script = $m[1] ?? '';

        $this->assertStringContainsString("'strict-dynamic'", $script);
        $this->assertMatchesRegularExpression("/'nonce-[A-Za-z0-9+\\/=]+'/", $script);
        $this->assertDoesNotMatchRegularExpression('/\bhttps?:(\s|$)/', $script,
            'script-src ما زال يفتح https: أو http: — ' . $script);
        $this->assertStringNotContainsString('cdn.jsdelivr.net', $csp, 'ما زالت السياسةُ تثق في شبكةٍ لم نعد نحمّل منها');

        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
    }

    public function test_the_legacy_xss_filter_is_disabled_not_enabled(): void
    {
        $this->get('/login')->assertHeader('X-XSS-Protection', '0');
    }

    /**
     * ═══ لا مكتبةَ من شبكةٍ خارجيّة ═══
     *
     * ‏Alpine كان بإصدارٍ عائم (‎3.x.x‎) من cdn.jsdelivr.net: أيُّ إصدارٍ
     * جديدٍ يصل المتصفّحَ بلا نشرٍ ولا اختبار، ومن ملك الشبكةَ ملك
     * الصفحة. والفاحصُ يعدّ كلَّ سكربتٍ خارجيٍّ بلا integrity ثغرة —
     * والبصمةُ لا تُوضع على رابطٍ عائم. فصارت محمولةً مع النظام.
     */
    public function test_the_app_layout_loads_its_libraries_from_our_own_domain(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertDoesNotMatchRegularExpression('/(src|href)="https:\/\/cdn\.jsdelivr\.net/', $layout,
            'ما زالت مكتبةٌ تُحمَّل من jsdelivr');

        foreach ([
            'vendor/alpinejs/alpine-3.17.2.min.js' => 40_000,
            'vendor/chartjs/chart-4.5.1.umd.min.js' => 150_000,
            'vendor/tom-select/tom-select-2.3.1.complete.min.js' => 40_000,
            'vendor/tom-select/tom-select-2.3.1.css' => 5_000,
        ] as $file => $min) {
            $this->assertFileExists(public_path($file), $file . ' غيرُ موجود');
            $this->assertGreaterThan($min, filesize(public_path($file)), $file . ' أصغرُ من أن يكون كاملاً');
            $this->assertStringContainsString($file, $layout, $file . ' لا يُشار إليه من التخطيط');
        }
    }

    /**
     * و‎X-Powered-By: PHP/8.4‎ تُمحى من طبقة PHP.
     *
     * لا يمكن فحصُها على ردّ الاختبار — PHP يضيفها في SAPI الخادم لا في
     * كائن الردّ — فيُفحص أنّ الوسيطَ يأمر بمحوها، ويُؤكَّد على الخادم
     * بـcurl بعد النشر.
     */
    public function test_the_middleware_strips_the_php_version_banner(): void
    {
        $src = file_get_contents(app_path('Http/Middleware/SecurityHeaders.php'));

        $this->assertStringContainsString("header_remove('X-Powered-By')", $src);
    }

    /** والوسيطُ عامٌّ لا في مجموعة web — وإلا عاد 404 عارياً. */
    public function test_the_middleware_is_registered_globally(): void
    {
        $bootstrap = file_get_contents(base_path('bootstrap/app.php'));

        $this->assertStringContainsString('$middleware->append(\App\Http\Middleware\SecurityHeaders::class)', $bootstrap);
        $this->assertStringNotContainsString("appendToGroup('web', \\App\\Http\\Middleware\\SecurityHeaders::class)", $bootstrap);
    }
}
