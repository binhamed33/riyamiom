<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Suggestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * قناة العودة عند الموظّف: ردّ المطوّر وحالة اقتراحه يصلانه، بلغته،
 * ومرّة واحدة — ودون أن يُمَسّ نصّ اقتراحه.
 */
class SuggestionReplySyncTest extends TestCase
{
    use RefreshDatabase;

    private function link(): void
    {
        config()->set('panel.ingest_url', 'https://panel.test');
        config()->set('panel.ingest_token', str_repeat('t', 48));
    }

    private function suggestion(User $user, array $attrs = []): Suggestion
    {
        return Suggestion::create(array_merge([
            'user_id' => $user->id,
            'title' => 'عنوان',
            'content' => 'نص الاقتراح الأصلي',
            'status' => Suggestion::STATUS_PENDING,
            'delivery_state' => 'sent',
        ], $attrs));
    }

    private function panelSays(array $rows): void
    {
        Http::fake([
            'panel.test/ingest/replies' => Http::response(['ok' => true, 'replies' => $rows], 200),
        ]);
    }

    public function test_a_reply_from_the_panel_reaches_the_employee(): void
    {
        $this->link();
        $user = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $suggestion = $this->suggestion($user);

        $this->panelSays([[
            'remote_id' => $suggestion->id,
            'status' => 'pending',
            'panel_status' => 'planned',
            'reply' => 'سنُدرجه في تحديث الشهر القادم',
            'replied_at' => now()->toIso8601String(),
        ]]);

        $this->artisan('suggestions:sync-replies')->assertSuccessful();

        $suggestion->refresh();
        $this->assertSame('سنُدرجه في تحديث الشهر القادم', $suggestion->developer_reply);
        $this->assertSame('planned', $suggestion->panel_status);
        $this->assertFalse($suggestion->reply_read);
        $this->assertNotNull($suggestion->replied_at);
        $this->assertSame('نص الاقتراح الأصلي', $suggestion->content);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'title_key' => 'app.notif_suggestion_reply_title',
            'notifiable_id' => $suggestion->id,
        ]);
    }

    public function test_the_notification_reads_in_the_language_the_employee_chose(): void
    {
        $this->link();
        $arabic = User::factory()->create(['role' => 'lawyer', 'is_active' => true, 'locale' => 'ar']);
        $english = User::factory()->create(['role' => 'lawyer', 'is_active' => true, 'locale' => 'en']);

        $rows = [];
        foreach ([$arabic, $english] as $user) {
            $rows[] = [
                'remote_id' => $this->suggestion($user)->id,
                'status' => 'implemented',
                'panel_status' => 'done',
                'reply' => 'شكراً — نُفِّذ',
                'replied_at' => now()->toIso8601String(),
            ];
        }

        $this->panelSays($rows);
        $this->artisan('suggestions:sync-replies')->assertSuccessful();

        $arabicDone = Notification::where('user_id', $arabic->id)
            ->where('title_key', 'app.notif_suggestion_done_title')->firstOrFail();
        $englishDone = Notification::where('user_id', $english->id)
            ->where('title_key', 'app.notif_suggestion_done_title')->firstOrFail();

        $this->assertSame(__('app.notif_suggestion_done_title', [], 'ar'), $arabicDone->localizedTitle('ar'));
        $this->assertSame(__('app.notif_suggestion_done_title', [], 'en'), $englishDone->localizedTitle('en'));
        $this->assertNotSame($arabicDone->localizedTitle('ar'), $englishDone->localizedTitle('en'));
    }

    public function test_running_twice_does_not_notify_twice(): void
    {
        $this->link();
        $user = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $suggestion = $this->suggestion($user);

        $this->panelSays([[
            'remote_id' => $suggestion->id,
            'status' => 'implemented',
            'panel_status' => 'done',
            'reply' => 'نُفِّذ',
            'replied_at' => now()->toIso8601String(),
        ]]);

        $this->artisan('suggestions:sync-replies')->assertSuccessful();
        $suggestion->update(['reply_read' => true]);
        $this->artisan('suggestions:sync-replies')->assertSuccessful();

        $this->assertSame(1, Notification::where('user_id', $user->id)
            ->where('title_key', 'app.notif_suggestion_reply_title')->count());
        $this->assertSame(1, Notification::where('user_id', $user->id)
            ->where('title_key', 'app.notif_suggestion_done_title')->count());
        $this->assertTrue($suggestion->fresh()->reply_read);
    }

    public function test_an_unlinked_office_calls_nothing_and_changes_nothing(): void
    {
        config()->set('panel.ingest_url', null);
        config()->set('panel.ingest_token', null);

        Http::fake();
        $user = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $suggestion = $this->suggestion($user);

        $this->artisan('suggestions:sync-replies')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertNull($suggestion->fresh()->developer_reply);
        $this->assertSame(0, Notification::count());
    }

    public function test_a_panel_that_cannot_be_reached_leaves_the_office_untouched(): void
    {
        $this->link();
        $user = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $suggestion = $this->suggestion($user, ['developer_reply' => 'ردّ قديم', 'reply_read' => true]);

        Http::fake(['panel.test/*' => Http::response('', 500)]);

        $this->artisan('suggestions:sync-replies')->assertSuccessful();

        $suggestion->refresh();
        $this->assertSame('ردّ قديم', $suggestion->developer_reply);
        $this->assertTrue($suggestion->reply_read);
    }

    public function test_a_reply_for_a_suggestion_this_office_does_not_have_is_ignored(): void
    {
        $this->link();
        $user = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $this->suggestion($user);

        $this->panelSays([[
            'remote_id' => 999999,
            'status' => 'implemented',
            'panel_status' => 'done',
            'reply' => 'ردّ لمكتب آخر',
            'replied_at' => now()->toIso8601String(),
        ]]);

        $this->artisan('suggestions:sync-replies')->assertSuccessful();

        $this->assertSame(0, Suggestion::whereNotNull('developer_reply')->count());
        $this->assertSame(0, Notification::count());
    }

    public function test_the_employee_sees_the_exact_developer_state_not_a_guess(): void
    {
        $user = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $declined = $this->suggestion($user, ['panel_status' => 'declined']);
        $planned = $this->suggestion($user, ['panel_status' => 'planned']);
        $plain = $this->suggestion($user);

        $this->assertSame('declined', $declined->statusDisplay()['tone']);
        $this->assertSame('planned', $planned->statusDisplay()['tone']);
        $this->assertSame('reviewing', $plain->statusDisplay()['tone']);

        $this->actingAs($user)->get(route('suggestions.index'))
            ->assertOk()
            ->assertSee(__('app.suggestion_state_declined'))
            ->assertSee(__('app.suggestion_state_planned'));
    }
}
