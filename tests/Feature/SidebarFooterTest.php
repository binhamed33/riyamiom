<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * تذييلُ «المساعدة والتواصل» موضوعُه المساعدة — لا حسابُ المستخدم.
 *
 * كانت اللغةُ والملفُّ الشخصيُّ مكرَّرين فيه، وكلاهما في الشريط
 * العلويّ: كرةُ الأرض تبدّل اللغة، وقائمةُ الصورة تفتح الملفّ. وتكرارُ
 * المدخل في مكانين لا يزيد وصولاً — يزيد ضجيجاً.
 *
 * ولم يُحذف مسارٌ ولا صفحة: الحارسُ يتأكّد من ذلك تحديداً، فحذفُ
 * المدخل شيءٌ وحذفُ الوجهة شيءٌ آخر.
 */
class SidebarFooterTest extends TestCase
{
    use RefreshDatabase;

    /** المدخلان خرجا من التذييل — وبقيا في الشريط العلويّ. */
    public function test_language_and_profile_left_the_help_footer(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

        // مرّةً واحدةً لكلٍّ منهما: في الشريط العلويّ لا في التذييل
        $this->assertSame(1, substr_count($html, route('profile.edit')), 'الملفُّ الشخصيُّ ما زال مكرَّراً');
        // الوجهةُ تتبع لغةَ الجلسة (ar⇄en): يُعدّ المسارُ لا أحدُ طرفيه
        $this->assertSame(1, substr_count($html, url('/lang/')), 'اللغةُ ما زالت مكرَّرة');

        // وما بقي في التذييل من موضوعه
        $this->assertStringContainsString(route('guide'), $html);
        $this->assertStringContainsString(route('suggestions.index'), $html);
    }

    /** والوجهتان تُفتحان: حُذف المدخلُ لا الصفحة. */
    public function test_both_destinations_still_open(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($user)->get(route('profile.edit'))->assertOk();
        $this->actingAs($user)->get(route('language.switch', 'en'))->assertRedirect();
    }
}
