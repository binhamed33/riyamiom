<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * سكربت المساعد المضمَّن في القالب سليمُ الصياغة.
 *
 * ═══ العطل الذي وُضع له ═══
 *
 * القالب يمرّ بـphp -l فيبدو سليماً، وجافاسكربت المضمَّن فيه لا يفحصه
 * شيء: شُحن مرّةً `finally` بلا `try` — استبدالٌ آليّ لم يطابق موضعه —
 * فانهار المكوّن كلُّه عند أول فتح، والحزمة خضراء. لا يظهر إلا في
 * كونسول متصفّح المستخدم.
 *
 * الفحص يستخرج المكوّن كما يُشحن ويمرّره إلى Node — فإن غاب Node
 * (بيئة CI بلا جافاسكربت) تخطّى الاختبار ولم يتظاهر بالنجاح.
 */
class AssistantWidgetSyntaxTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_embedded_assistant_component_parses(): void
    {
        $node = trim((string) shell_exec('command -v node 2>/dev/null'));
        if ($node === '') {
            $this->markTestSkipped('Node غير متاح في هذه البيئة.');
        }

        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

        $start = mb_strpos($html, "Alpine.data('assistant'");
        $this->assertNotFalse($start, 'مكوّن المساعد غير موجود في الصفحة');
        $end = mb_strpos($html, '}));', $start);
        $component = mb_substr($html, $start, $end - $start + 4);

        // يُغلَّف تعبيراً حتى يقبله المفسّر كملفٍّ مستقل
        $wrapped = "const Alpine = { data: (n, f) => f() };\n" . $component;
        $file = tempnam(sys_get_temp_dir(), 'aiw') . '.js';
        file_put_contents($file, $wrapped);

        exec(escapeshellarg($node) . ' --check ' . escapeshellarg($file) . ' 2>&1', $out, $code);
        unlink($file);

        $this->assertSame(0, $code, "صياغة سكربت المساعد مكسورة:\n" . implode("\n", $out));
    }
}
