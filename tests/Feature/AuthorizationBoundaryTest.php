<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * الحدود التي تُلزم فعلاً — لا التي نظنّها.
 *
 * ملاحظة مقصودة: داخل المكتب الواحد يرى كل عضوٍ في الفريق كل القضايا.
 * هذا قرار تصميم لا خلل — مكتب محاماة صغير يعمل هكذا، والمتحكّم يعلنه
 * صراحةً في authorizeCaseAccess. فلا يُفحص هنا ما لم يُقصد منعه.
 *
 * الذي يُفحص: الحدود التي لو سقطت كانت تصعيد صلاحية حقيقياً — حساب
 * الموكّل في بوابة العملاء لا يدخل نظام المكتب، والموظّف العادي لا
 * يبلغ ما هو للمدير.
 */
class AuthorizationBoundaryTest extends TestCase
{
    use RefreshDatabase;

    /** المسارات التي يجب ألا يبلغها حساب بدور «موكّل» */
    private const OFFICE_ROUTES = [
        'cases.index', 'clients.index', 'sessions.index',
        'tasks.index', 'documents.index', 'users.index',
    ];

    public function test_a_client_role_account_cannot_reach_any_office_page(): void
    {
        $client = User::factory()->create(['role' => 'client', 'is_active' => true]);

        foreach (self::OFFICE_ROUTES as $name) {
            if (!\Illuminate\Support\Facades\Route::has($name)) {
                continue;
            }

            $status = $this->actingAs($client)->get(route($name))->status();

            $this->assertNotSame(200, $status,
                "حساب بدور «موكّل» فتح صفحة المكتب: {$name}");
        }
    }

    public function test_a_staff_member_cannot_manage_users(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);

        $status = $this->actingAs($staff)->get(route('users.index'))->status();

        $this->assertNotSame(200, $status, 'موظّف عادي بلغ إدارة المستخدمين');
    }

    public function test_a_deactivated_user_is_locked_out_even_with_a_valid_session(): void
    {
        // تعطيل الحساب يجب أن يسري فوراً لا عند انتهاء الجلسة
        $user = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $user->update(['is_active' => false]);

        $this->assertNotSame(200, $this->actingAs($user->fresh())->get(route('dashboard'))->status(),
            'حساب معطَّل ما زال يعمل بجلسته القديمة');
    }

    public function test_a_guest_reaches_no_office_page(): void
    {
        foreach (self::OFFICE_ROUTES as $name) {
            if (!\Illuminate\Support\Facades\Route::has($name)) {
                continue;
            }

            $this->get(route($name))->assertRedirect();
        }
    }

    public function test_a_document_cannot_be_downloaded_without_signing_in(): void
    {
        $case = LegalCase::factory()->create(['client_id' => Client::factory()->create()->id]);

        $doc = \App\Models\Document::factory()->create([
            'case_id' => $case->id,
            'client_visible' => true,
        ]);

        $this->get(route('documents.download', $doc))->assertRedirect();
    }

    public function test_a_client_role_account_cannot_reach_the_legal_assistant(): void
    {
        // تعليمة المساعد تحقن قائمة قضايا المكتب بأسماء موكّليها،
        // فبلوغُها من حسابٍ دوره «موكّل» تسريبٌ مباشر — ويستهلك حصّة
        // مفتاح المكتب أيضاً.
        $client = User::factory()->create(['role' => 'client', 'is_active' => true]);

        $this->assertNotSame(200,
            $this->actingAs($client)->postJson(route('assistant.chat'), ['message' => 'ما قضايا المكتب؟'])->status(),
            'حساب موكّل بلغ المساعد القانوني — وتعليمته تحمل قضايا المكتب');

        $this->assertNotSame(200,
            $this->actingAs($client)->get(route('assistant.history'))->status());
    }
}
