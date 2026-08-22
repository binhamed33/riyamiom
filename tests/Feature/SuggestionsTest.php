<?php

namespace Tests\Feature;

use App\Models\Suggestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * صندوق الاقتراحات — أُعيد بعد أن حُذف في جولة سابقة، وموضعه الآن
 * قسم «المساعدة والتواصل» أسفل القائمة لا وسط عناصر العمل اليومي.
 */
class SuggestionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();   // لا نرسل إلى ديسكورد أثناء الاختبار
    }

    public function test_the_page_still_works_at_the_same_url(): void
    {
        $user = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);

        $this->actingAs($user)->get('/suggestions')->assertOk();
        $this->assertSame('/suggestions', parse_url(route('suggestions.index'), PHP_URL_PATH));
    }

    public function test_a_team_member_can_submit_a_suggestion(): void
    {
        $user = User::factory()->create(['role' => 'staff', 'is_active' => true]);

        $this->actingAs($user)->post(route('suggestions.store'), [
            'content' => 'أقترح إضافة تنبيه قبل موعد الجلسة بيوم كامل لتفادي نسيان التحضير.',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('suggestions', ['user_id' => $user->id, 'status' => 'pending']);
    }

    public function test_short_suggestions_are_rejected(): void
    {
        $user = User::factory()->create(['role' => 'staff', 'is_active' => true]);

        $this->actingAs($user)->post(route('suggestions.store'), ['content' => 'قصير'])
            ->assertSessionHasErrors('content');

        $this->assertDatabaseCount('suggestions', 0);
    }

    public function test_each_user_sees_only_their_own_suggestions(): void
    {
        $mine = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $other = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);

        Suggestion::create(['user_id' => $other->id, 'content' => 'اقتراح زميل آخر لا يخصني إطلاقاً']);
        Suggestion::create(['user_id' => $mine->id, 'content' => 'اقتراحي الشخصي الذي كتبته بنفسي']);

        $this->actingAs($mine)->get('/suggestions')
            ->assertOk()
            ->assertSee('اقتراحي الشخصي', false)
            ->assertDontSee('اقتراح زميل آخر', false);
    }

    public function test_clients_are_blocked_even_by_direct_url(): void
    {
        $client = User::factory()->create(['role' => 'client', 'is_active' => true]);

        // الرفض قد يكون 403 أو تحويلاً بعيداً عن الصفحة — المهم ألا يصل ولا يكتب
        $get = $this->actingAs($client)->get('/suggestions');
        $this->assertContains($get->status(), [302, 403]);

        $post = $this->actingAs($client)->post(route('suggestions.store'), [
            'content' => 'محاولة إرسال اقتراح من حساب عميل وهو غير مخوّل بذلك',
        ]);
        $this->assertContains($post->status(), [302, 403]);

        $this->assertDatabaseCount('suggestions', 0);
    }

    public function test_only_the_developer_can_reply_or_delete(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $suggestion = Suggestion::create(['user_id' => $admin->id, 'content' => 'اقتراح للاختبار على مسار الرد']);

        $lawyer = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $denied = $this->actingAs($lawyer)
            ->post(route('suggestions.reply', $suggestion), ['reply' => 'رد غير مصرح به']);

        $this->assertContains($denied->status(), [302, 403]);
        $this->assertNull($suggestion->fresh()->developer_reply, 'محامٍ تمكّن من الرد على الاقتراحات');

        $dev = User::factory()->create(['role' => 'developer', 'is_active' => true]);
        $this->actingAs($dev)
            ->post(route('suggestions.reply', $suggestion), ['reply' => 'شكراً، نُفّذ الاقتراح.'])
            ->assertRedirect();

        $this->assertSame('شكراً، نُفّذ الاقتراح.', $suggestion->fresh()->developer_reply);
    }

    public function test_link_sits_in_the_help_section_not_among_daily_work(): void
    {
        $user = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();

        $suggestionsAt = strpos($html, route('suggestions.index'));
        $casesAt = strpos($html, route('cases.index'));
        $helpTitleAt = strpos($html, __('app.help_section'));

        $this->assertNotFalse($suggestionsAt, 'رابط صندوق الاقتراحات مفقود من القائمة');
        $this->assertGreaterThan($casesAt, $suggestionsAt, 'الاقتراحات تسبق القضايا في القائمة');
        $this->assertGreaterThan($helpTitleAt, $suggestionsAt, 'الاقتراحات خارج قسم المساعدة');
    }

    public function test_reports_link_is_visible_to_lawyers_who_may_open_it(): void
    {
        // كان الرابط محجوباً خلف شرط إداري رغم أن المسار مسموح للمحامي
        $lawyer = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);

        $this->actingAs($lawyer)->get('/dashboard')
            ->assertOk()
            ->assertSee(route('reports.index'), false)
            ->assertSee(route('evaluations.index'), false);
    }

    public function test_client_sidebar_hides_team_only_links(): void
    {
        $client = User::factory()->create(['role' => 'client', 'is_active' => true]);
        $response = $this->actingAs($client)->get('/dashboard');

        if ($response->status() === 200) {
            $response->assertDontSee(route('suggestions.index'), false);
        }
        $this->assertTrue(true);
    }
}
