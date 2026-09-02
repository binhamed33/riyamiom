<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\MysqlCredentialsFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ثلاثُ فجواتٍ صغيرةٍ وُجدت في مسح الإعدادات والمصادقة.
 */
class HardeningTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ملفُّ بيانات الاتصال بإذن صاحبه وحدَه — ولا ملفَّ فارغاً يُترك.
     *
     * كان يُكتب 0644 في /tmp المشترك: كلمةُ مرور قاعدة المكتب مقروءةً
     * لكلّ مستخدمٍ على الخادم طوالَ مدّة النسخ — كلَّ يوم.
     */
    public function test_the_credentials_file_is_owner_only_and_leaves_no_sibling(): void
    {
        $path = MysqlCredentialsFile::write('127.0.0.1', 3306, 'office_user', 's3cr3t');

        try {
            $this->assertSame(0600, fileperms($path) & 0777, sprintf('الإذن %o لا 0600', fileperms($path) & 0777));
            $this->assertStringContainsString('password="s3cr3t"', (string) file_get_contents($path));
            $this->assertFileDoesNotExist($path . '.cnf', 'ملفٌّ شقيقٌ بلاحقة .cnf');
        } finally {
            @unlink($path);
        }
    }

    /**
     * تسجيلُ الخروج لم يعد مستثنًى من CSRF — صفحةٌ غريبةٌ لا تُخرج أحداً.
     *
     * Laravel يعطّل فحصَ الرمز في بيئة الاختبار كلِّها (runningUnitTests)،
     * فلا يُرى الأثرُ عبر طلبٍ حقيقيّ. يُفحص الحكمُ نفسُه: مسارُ الخروج
     * ليس في قائمة الاستثناء، وكلُّ نموذجِ خروجٍ في القوالب يحمل الرمز.
     */
    public function test_logout_is_not_exempt_from_csrf(): void
    {
        $middleware = app(\App\Http\Middleware\VerifyCsrfToken::class);

        $except = (new \ReflectionProperty($middleware, 'except'))->getValue($middleware);
        $this->assertNotContains('logout', $except, 'تسجيلُ الخروج مستثنًى من CSRF');

        $inExcept = new \ReflectionMethod($middleware, 'inExceptArray');
        $request = \Illuminate\Http\Request::create('/logout', 'POST');
        $this->assertFalse($inExcept->invoke($middleware, $request), 'طلبُ الخروج يمرّ بلا رمز');

        // ولا نموذجَ خروجٍ بلا رمز — وإلا صار الاستثناءُ ضرورةً من جديد
        foreach (glob(resource_path('views/**/*.blade.php')) + glob(resource_path('views/*.blade.php')) as $file) {
            $html = (string) file_get_contents($file);
            $forms = preg_match_all('/action="\{\{ route\(\'logout\'\) \}\}"[^>]*>\s*(@csrf|<input[^>]*_token)/', $html);
            $total = substr_count($html, "route('logout')");
            $this->assertSame($total, $forms, basename($file) . ': نموذجُ خروجٍ بلا @csrf');
        }
    }

    /**
     * ترويسةُ X-Forwarded-For من عميلٍ لا تغيّر عنوانَه.
     *
     * كان الوكلاءُ «*»: أيُّ عميلٍ يقرّر ما هو عنوانُه، فيتجاوز حدَّ
     * المحاولات ويكتب في سجلّ التدقيق ما يشاء.
     */
    public function test_a_spoofed_forwarded_for_header_is_ignored(): void
    {
        $seen = null;
        \Illuminate\Support\Facades\Route::get('/__ip-probe', function (\Illuminate\Http\Request $r) use (&$seen) {
            $seen = $r->ip();
            return 'ok';
        });

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->withHeaders(['X-Forwarded-For' => '10.0.0.1'])
            ->get('/__ip-probe')->assertOk();

        $this->assertSame('203.0.113.9', $seen, 'عنوانٌ مزوَّرٌ في الترويسة صُدِّق');
    }
}
