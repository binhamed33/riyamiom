<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PanelDirectives;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * أوامرُ اللوحة تُطبَّق بيد المكتب — ويُرَدّ ما لا يُفهم.
 *
 * «بإمكان المطوّر تعديل رتبة المستخدمين لأي مكتب من لوحة المبرمجين»:
 * الأمرُ يصل مع ردّ النبضة، والمكتبُ يطبّقه هنا ويقرّ بالنتيجة.
 * والقادمُ من اللوحة مدخلٌ كأيّ مدخل: رتبةٌ من قائمةٍ مسمّاة، ونوعٌ
 * من قاموسٍ مغلق — لا يُخمَّن ولا يُوسَّع.
 */
class PanelDirectivesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_role_change_order_changes_the_role_and_tells_what_it_did(): void
    {
        $user = User::factory()->create(['role' => 'staff', 'email' => 'ahmed@office.om', 'name' => 'أحمد']);

        $result = PanelDirectives::apply([
            'id' => 1,
            'type' => 'set_user_role',
            'payload' => ['email' => 'Ahmed@Office.om', 'role' => 'admin'],
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame('admin', $user->refresh()->role);
        $this->assertStringContainsString('أحمد', $result['message']);
        $this->assertStringContainsString('staff', $result['message'], 'الرتبة القديمة لا تُذكر — فلا يُعرف ما تغيّر');
    }

    public function test_an_unknown_email_is_refused_with_its_reason(): void
    {
        $result = PanelDirectives::apply([
            'type' => 'set_user_role',
            'payload' => ['email' => 'ghost@office.om', 'role' => 'admin'],
        ]);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('لا مستخدم', $result['message']);
    }

    /** رتبةٌ من خارج القاموس تُرَدّ — ولو جاءت من اللوحة نفسها. */
    public function test_a_role_outside_the_dictionary_is_refused(): void
    {
        $user = User::factory()->create(['role' => 'staff', 'email' => 'sara@office.om']);

        $result = PanelDirectives::apply([
            'type' => 'set_user_role',
            'payload' => ['email' => 'sara@office.om', 'role' => 'superadmin'],
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame('staff', $user->refresh()->role, 'رتبةٌ مختلَقة كُتبت');
    }

    public function test_an_unknown_directive_type_is_refused_not_guessed(): void
    {
        $result = PanelDirectives::apply(['type' => 'drop_all_tables', 'payload' => []]);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('غير معروف', $result['message']);
    }

    /** والتكرارُ آمن: نفسُ الرتبة مرّتين إقرارُ نجاحٍ لا خطأ. */
    public function test_reapplying_the_same_role_is_a_calm_success(): void
    {
        User::factory()->create(['role' => 'admin', 'email' => 'x@office.om']);

        $result = PanelDirectives::apply([
            'type' => 'set_user_role',
            'payload' => ['email' => 'x@office.om', 'role' => 'admin'],
        ]);

        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('نفسُها', $result['message']);
    }
}
