<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Automation\AutomationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * §54: ما يحتاجه من لا يستعمل فأرة، ومن يقرأ الشاشة بأذنه.
 *
 * أخطر ما وُجد: لم تكن في النظام حلقةُ تركيز ظاهرة إلا على حقول
 * ‎.form-input‎ — فمن يتنقّل بـTab كان يتحرّك أعمى بين الأزرار.
 */
class AccessibilityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    public function test_every_page_carries_a_visible_focus_ring(): void
    {
        $html = $this->actingAs($this->admin())->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString(':focus-visible', $html, 'لا حلقة تركيز في النظام');
        $this->assertStringContainsString('outline: 2px solid var(--accent)', $html);
    }

    public function test_no_control_kills_its_focus_ring_without_a_replacement(): void
    {
        $bad = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));

        foreach ($files as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            foreach (file($file->getPathname()) as $n => $line) {
                if (!str_contains($line, 'focus:outline-none')) {
                    continue;
                }

                // إزالة الحلقة مقبولة إن وُضع بديلٌ ظاهر مكانها
                if (str_contains($line, 'focus:ring') || str_contains($line, 'focus:border')) {
                    continue;
                }

                // لوحة الأوامر: حقلٌ واحد يُركَّز عليه تلقائياً عند الفتح،
                // والمؤشّر النابض فيه هو الدليل — لا شيء آخر يُتنقّل إليه
                if (str_contains($file->getFilename(), 'command-palette')) {
                    continue;
                }

                $bad[] = $file->getFilename() . ':' . ($n + 1);
            }
        }

        $this->assertSame([], $bad, 'عناصر بلا حلقة تركيز ولا بديل: ' . implode('، ', $bad));
    }

    public function test_the_filter_controls_announce_themselves(): void
    {
        $admin = $this->admin();
        AutomationEngine::seedDefaults($admin->id);

        foreach ([
            '/automations' => ['autoQ', 'autoState', 'autoSort'],
            '/case-templates' => ['tplQ', 'tplState', 'tplSort'],
        ] as $url => $ids) {
            $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();

            foreach ($ids as $id) {
                $this->assertMatchesRegularExpression(
                    '/<label[^>]*for="' . $id . '"/',
                    $html,
                    "الحقل {$id} في {$url} بلا تسمية يقرأها قارئ الشاشة"
                );
            }
        }
    }

    public function test_the_ai_panels_announce_whether_they_are_open(): void
    {
        $admin = $this->admin();

        foreach (['/automations' => 'autoAiPanel', '/case-templates' => 'tplAiPanel'] as $url => $panel) {
            $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('aria-controls="' . $panel . '"', $html);
            $this->assertStringContainsString('aria-expanded', $html);
            $this->assertStringContainsString('id="' . $panel . '"', $html);
        }
    }

    public function test_generation_errors_are_announced_not_only_shown(): void
    {
        $html = $this->actingAs($this->admin())->get('/automations')->getContent();

        $this->assertStringContainsString('role="alert"', $html, 'رسالة الخطأ تُعرض ولا تُنطق');
    }
}
