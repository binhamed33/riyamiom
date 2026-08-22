<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Suggestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * حذف الاقتراح في المكتب: صاحبه ومدير المكتب والمطوّر — لا الزميل.
 * والحذف ناعم: يختفي ولا يضيع.
 */
class SuggestionDeleteOfficeTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role = 'lawyer'): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    private function suggestion(User $owner): Suggestion
    {
        return Suggestion::create([
            'user_id' => $owner->id,
            'title' => 'عنوان',
            'content' => 'نصّ الاقتراح',
            'status' => Suggestion::STATUS_PENDING,
            'delivery_state' => 'sent',
        ]);
    }

    public function test_an_employee_deletes_their_own_suggestion(): void
    {
        $owner = $this->staff();
        $suggestion = $this->suggestion($owner);

        $this->actingAs($owner)
            ->delete(route('suggestions.destroy', $suggestion))
            ->assertRedirect();

        $this->assertSoftDeleted('suggestions', ['id' => $suggestion->id]);
    }

    public function test_an_employee_cannot_delete_a_colleagues_suggestion(): void
    {
        $suggestion = $this->suggestion($this->staff());

        // المكتب يحوّل رفض الصلاحية إلى رسالة مفهومة بدل صفحة 403 عارية
        $this->actingAs($this->staff())
            ->delete(route('suggestions.destroy', $suggestion))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', __('app.suggestion_delete_denied'));

        $this->assertNotSoftDeleted('suggestions', ['id' => $suggestion->id]);
    }

    public function test_the_office_manager_deletes_any_suggestion(): void
    {
        $suggestion = $this->suggestion($this->staff());

        $this->actingAs($this->staff('admin'))
            ->delete(route('suggestions.destroy', $suggestion))
            ->assertRedirect();

        $this->assertSoftDeleted('suggestions', ['id' => $suggestion->id]);
    }

    public function test_the_developer_deletes_any_suggestion(): void
    {
        $suggestion = $this->suggestion($this->staff());

        $this->actingAs($this->staff('developer'))
            ->delete(route('suggestions.destroy', $suggestion))
            ->assertRedirect();

        $this->assertSoftDeleted('suggestions', ['id' => $suggestion->id]);
    }

    public function test_a_client_cannot_reach_it_at_all(): void
    {
        $suggestion = $this->suggestion($this->staff());

        // العميل يُردّ قبل أن يصل المتحكّم أصلاً — بوابة الدور لا الزر
        $this->actingAs($this->staff('client'))
            ->delete(route('suggestions.destroy', $suggestion))
            ->assertRedirect();

        $this->assertNotSoftDeleted('suggestions', ['id' => $suggestion->id]);
    }

    public function test_the_deleted_suggestion_leaves_the_employees_list(): void
    {
        $owner = $this->staff();
        $kept = $this->suggestion($owner);
        $dropped = $this->suggestion($owner);

        $this->actingAs($owner)->delete(route('suggestions.destroy', $dropped));

        $this->actingAs($owner)->get(route('suggestions.index'))
            ->assertOk()
            ->assertSee($kept->content)
            ->assertDontSee(route('suggestions.destroy', $dropped));

        $this->assertSame(1, Suggestion::where('user_id', $owner->id)->count());
    }

    public function test_notifications_pointing_at_it_go_with_it(): void
    {
        $owner = $this->staff();
        $suggestion = $this->suggestion($owner);

        \App\Support\Notify::send(
            userId: $owner->id,
            titleKey: 'app.notif_suggestion_reply_title',
            messageKey: 'app.notif_passthrough',
            params: ['text' => 'ردّ'],
            notifiableType: Suggestion::class,
            notifiableId: $suggestion->id,
        );

        $this->actingAs($owner)->delete(route('suggestions.destroy', $suggestion));

        $this->assertSame(0, Notification::where('notifiable_id', $suggestion->id)->count());
    }

    public function test_the_text_is_not_destroyed_only_hidden(): void
    {
        $owner = $this->staff();
        $suggestion = $this->suggestion($owner);

        $this->actingAs($owner)->delete(route('suggestions.destroy', $suggestion));

        $kept = Suggestion::withTrashed()->find($suggestion->id);

        $this->assertNotNull($kept);
        $this->assertSame('نصّ الاقتراح', $kept->content);
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $suggestion = $this->suggestion($this->staff());

        $this->delete(route('suggestions.destroy', $suggestion))->assertRedirect(route('login'));

        $this->assertNotSoftDeleted('suggestions', ['id' => $suggestion->id]);
    }
}
