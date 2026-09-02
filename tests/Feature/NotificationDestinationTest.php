<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\LegalCase;
use App\Models\Notification;
use App\Models\Task;
use App\Models\User;
use App\Support\Notify;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * الإشعارُ يوصل إلى ما يُخبر عنه.
 *
 * ═══ ما وُضعت له ═══
 *
 * كان الإشعارُ نصّاً لا يفعل شيئاً عند نقره: يقرأ الموظّفُ «أُسندت
 * إليك مهمّة» ثمّ يبحث عنها بنفسه في قائمةٍ من ستّين. والإشعارُ الذي
 * لا يُوصل إلى موضوعه نصفُ إشعار.
 */
class NotificationDestinationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function makeCase(): LegalCase
    {
        $client = Client::create(['name' => 'موكّل', 'type' => 'individual', 'national_id' => '1234567', 'phone' => '96891234567']);

        return LegalCase::create([
            'case_number' => 'م/2026/1',
            'title' => 'قضية',
            'description' => 'وصف',
            'type' => 'مدني',
            'court' => 'المحكمة',
            'opponent' => 'خصم',
            'status' => 'active',
            'priority' => 'medium',
            'client_id' => $client->id,
            'created_by' => $this->user->id,
            'opened_at' => now(),
        ]);
    }

    /** مهمّةٌ مُسندة ⇐ صفحةُ المهمّة نفسِها. */
    public function test_a_task_notification_lands_on_the_task(): void
    {
        $case = $this->makeCase();

        $task = Task::create([
            'title' => 'إعداد مذكرة',
            'case_id' => $case->id,
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id,
            'status' => 'pending',
            'priority' => 'high',
            'due_date' => now()->addDays(3),
        ]);

        $notification = Notify::send(
            $this->user->id,
            'app.notif_task_assigned_title',
            'app.notif_task_assigned_body',
            ['task' => $task->title],
            Notification::TYPE_INFO,
            Task::class,
            $task->id,
        );

        $this->actingAs($this->user)
            ->get(route('notifications.open', $notification))
            ->assertRedirect(route('tasks.show', $task->id));

        $this->assertTrue($notification->fresh()->is_read, 'فُتح الإشعارُ وبقي غيرَ مقروء');
    }

    /** وإشعارُ النسخة الاحتياطية ⇐ صفحةُ النسخ، ولو بلا كائنٍ مرتبط. */
    public function test_a_backup_notification_lands_on_the_backup_page(): void
    {
        $notification = Notify::send(
            $this->user->id,
            'app.notif_backup_remind_title',
            'app.notif_backup_remind_body',
            ['date' => '2026-09-01', 'size' => '0.1'],
        );

        $this->actingAs($this->user)
            ->get(route('notifications.open', $notification))
            ->assertRedirect(route('backup.index'));
    }

    /** وكائنٌ حُذف بعد إشعاره لا يرمي 404 في وجه من نقر. */
    public function test_a_deleted_subject_falls_back_to_the_list(): void
    {
        $case = $this->makeCase();

        $notification = Notify::send(
            $this->user->id,
            'app.notif_case_status_title',
            'app.notif_case_status_body',
            ['case' => $case->title, 'from' => 'جارية', 'to' => 'محكومة'],
            Notification::TYPE_INFO,
            LegalCase::class,
            $case->id,
        );

        $case->forceDelete();

        // لا كائنَ ⇐ يُقرأ الموضوعُ من المفتاح: «case» ⇐ قائمةُ القضايا
        $this->actingAs($this->user)
            ->get(route('notifications.open', $notification))
            ->assertRedirect(route('cases.index'));
    }

    /** ولا يفتح أحدٌ إشعارَ غيره. */
    public function test_a_stranger_cannot_open_someone_elses_notification(): void
    {
        $other = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);

        $notification = Notify::send($other->id, 'app.notif_backup_remind_title', 'app.notif_backup_remind_body');

        // المنظومةُ تحوّل رفضَ الصلاحية إلى رجوعٍ برسالةٍ مقروءة بدل
        // صفحةِ 403 عارية — فيُفحص الأثرُ لا الرمز
        $this->actingAs($this->user)
            ->get(route('notifications.open', $notification))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');

        $this->assertFalse($notification->fresh()->is_read, 'عُلّم إشعارُ غيره مقروءاً');
    }

    /** وكلُّ نوعٍ في الجدول له وجهة — لا إشعارَ يقف مكانَه. */
    public function test_every_notification_kind_has_a_destination(): void
    {
        $orphans = [];

        foreach ([
            'app.notif_task_assigned_title', 'app.notif_task_done_title', 'app.notif_session_title',
            'app.notif_case_status_title', 'app.notif_leave_new_title', 'app.notif_leave_approved_title',
            'app.notif_suggestion_reply_title', 'app.notif_reminder_title', 'app.notif_chat_title',
            'app.notif_auto_task_title', 'app.notif_auto_rule_title', 'app.notif_sub_expired_title',
            'app.notif_backup_remind_title', 'app.notif_backup_stale_title', 'app.notif_wa_failed_title',
        ] as $key) {
            $notification = Notify::send($this->user->id, $key, $key);

            if ($notification?->destination() === null) {
                $orphans[] = $key;
            }
        }

        $this->assertSame([], $orphans, "أنواعٌ من الإشعارات بلا وجهة:\n" . implode("\n", $orphans));
    }
}
