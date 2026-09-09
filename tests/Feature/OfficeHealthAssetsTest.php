<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * فحصُ الصحّة يطلب مكتباتِ الواجهة كما يطلبها المتصفّح.
 *
 * ═══ ما وقع ═══
 *
 * ‏Alpine نُقل إلى public/lib، والاختبارُ تأكّد أنّه على القرص فمرّ
 * أخضر. لكنّ .gitignore ابتلعه فلم يُرفع، والخادمُ ردّ 404، فانزلق
 * المحتوى تحت الشريط الجانبيّ في كلّ مكتبٍ ومات كلُّ زرّ — وفحصُ
 * الصحّة بعد النشر قال «كلُّ الفحوص سليمة».
 *
 * ‏404 على ملفٍّ ساكنٍ يردّه nginx مباشرةً: لا يمرّ بـPHP، فلا سطرَ في
 * السجلّ ولا رقمَ في النبضة. الفحصُ الصادقُ الوحيد هو طلبُ الملفّ.
 */
class OfficeHealthAssetsTest extends TestCase
{
    use RefreshDatabase;

    private function healthWith(array $responses): \Illuminate\Testing\PendingCommand
    {
        config(['app.url' => 'https://office.example.test']);
        Http::fake($responses);

        return $this->artisan('office:health');
    }

    /** خادمٌ يردّ 404 على المكتبة ⇐ الفحصُ يسقط ويقول أيُّ ملفٍّ ولماذا. */
    public function test_a_missing_library_on_the_server_fails_the_health_check(): void
    {
        $this->healthWith([
            'office.example.test/lib/alpinejs/*' => Http::response('', 404),
            'office.example.test/lib/*' => fn ($req) => Http::response(str_repeat('x', 60_000), 200),
        ])
            ->expectsOutputToContain('lib/alpinejs/alpine-3.17.2.min.js — الخادم يردّ 404')
            ->assertExitCode(1);
    }

    /** وخادمٌ يخدمها كاملةً ⇐ الأصولُ سليمة. */
    public function test_served_libraries_pass(): void
    {
        $sizes = [];
        foreach (glob(public_path('lib/*/*')) as $f) {
            $sizes[] = filesize($f);
        }
        $big = str_repeat('x', max($sizes) + 1);

        $this->healthWith(['office.example.test/lib/*' => Http::response($big, 200)])
            ->expectsOutputToContain('lib/alpinejs/alpine-3.17.2.min.js يُخدَم');
    }

    /** وملفٌّ يصل مبتوراً — وكيلٌ قصّه، أو نسخةٌ قديمة — ليس «يُخدَم». */
    public function test_a_truncated_library_is_reported(): void
    {
        $this->healthWith(['office.example.test/lib/*' => Http::response('tiny', 200)])
            ->expectsOutputToContain('يصل ناقصاً')
            ->assertExitCode(1);
    }

    /** والقائمةُ من التخطيط نفسِه: كلُّ asset('lib/…') فيه يُفحص. */
    public function test_every_library_referenced_by_the_layout_is_checked(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        preg_match_all("/asset\('(lib\/[^']+)'\)/", $layout, $m);

        $this->assertGreaterThanOrEqual(4, count(array_unique($m[1])), 'التخطيطُ لا يشير إلى المكتبات المحمولة');

        $cmd = $this->healthWith(['office.example.test/lib/*' => Http::response(str_repeat('x', 300_000), 200)]);

        foreach (array_unique($m[1]) as $path) {
            $cmd->expectsOutputToContain($path . ' يُخدَم');
        }
    }
}
