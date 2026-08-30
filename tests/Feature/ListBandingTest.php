<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * قوائم اللوحة تُفصَل بتناوب اللون لا بخطٍّ بين كل سطرين.
 *
 * ═══ لماذا ═══
 *
 * الخطّ الفاصل بين كل سطرين يصنع على قائمةٍ طويلة شبكةً من الخطوط
 * الأفقيّة تزاحم النصّ وتُتعب العين. والتناوب يفصل بلا خط: فرقٌ في
 * الأرضيّة يكفي لتمييز السطر ولا يُقرأ لوناً ثانياً — وهو ما تفعله
 * جداول الحساب منذ الورق.
 *
 * ولا تُغيَّر ألوان الواجهة: السطر الفرديّ يبقى على لون البطاقة،
 * والزوجيّ يأخذ درجةً واحدة عنه.
 */
class ListBandingTest extends TestCase
{
    use RefreshDatabase;

    private function page(): string
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        return $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();
    }

    public function test_the_dashboard_lists_are_banded_not_ruled(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('class="md-zebra"', $html);
        $this->assertStringNotContainsString('divide-y divide-gray-50', $html);
    }

    /** والتناوب معرَّف في الوضعين — لا في الفاتح وحده. */
    public function test_the_band_colour_is_defined_for_both_themes(): void
    {
        $html = $this->page();

        $this->assertMatchesRegularExpression('/:root\s*\{[^}]*--zebra:/s', $html);
        $this->assertMatchesRegularExpression('/\[data-theme="dark"\]\s*\{[^}]*--zebra:/s', $html);
    }

    /**
     * والتظليل عند المرور يغلب التناوب.
     *
     * لولا ذلك لبقي السطر الزوجيّ على لونه تحت المؤشّر، فيبدو أنّ
     * نصف الأسطر لا يستجيب. و`:where` يجرّد القاعدة من وزنها فتُغلَب.
     */
    public function test_hover_outranks_the_band(): void
    {
        $this->assertStringContainsString(
            '.md-zebra > :where(*:nth-child(even))',
            $this->page(),
        );
    }
}
