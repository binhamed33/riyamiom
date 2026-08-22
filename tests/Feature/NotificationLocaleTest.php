<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use App\Support\Notify;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * لغة الإشعارات.
 *
 * العطل: نصّ الإشعار كان يُكتب حرفياً في القاعدة وقت الإنشاء — بعضه
 * عربي وبعضه إنجليزي — فيصل الموظّف إشعارٌ بلغة لم يخترها، ولا سبيل
 * لتغييره بعد حفظه. والاختيار نفسه كان في الجلسة وحدها فيضيع بالخروج.
 *
 * الآن: يُحفظ المفتاح ومعاملاته، ويُبنى النصّ وقت القراءة بلغة قارئه.
 * والإشعارات القديمة تبقى بنصّها الحرفي — لا تُعدَّل ولا تُحذف.
 */
class NotificationLocaleTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $locale = 'ar'): User
    {
        return User::factory()->create(['role' => 'lawyer', 'is_active' => true, 'locale' => $locale]);
    }

    public function test_one_notification_reads_in_each_recipients_own_language(): void
    {
        $user = $this->user('ar');

        Notify::send(
            userId: $user->id,
            titleKey: 'app.notif_task_assigned_title',
            messageKey: 'app.notif_task_assigned_body',
            params: ['task' => 'مراجعة العقد'],
        );

        $n = Notification::firstOrFail();

        // نفس الصفّ — لغتان
        $this->assertSame(__('app.notif_task_assigned_title', [], 'ar'), $n->localizedTitle('ar'));
        $this->assertSame(__('app.notif_task_assigned_title', [], 'en'), $n->localizedTitle('en'));
        $this->assertNotSame($n->localizedTitle('ar'), $n->localizedTitle('en'));

        // والمعاملات تُحقن في اللغتين
        $this->assertStringContainsString('مراجعة العقد', $n->localizedMessage('ar'));
        $this->assertStringContainsString('مراجعة العقد', $n->localizedMessage('en'));
    }

    public function test_changing_the_language_changes_notifications_already_received(): void
    {
        $user = $this->user('ar');

        Notify::send(
            userId: $user->id,
            titleKey: 'app.notif_leave_approved_title',
            messageKey: 'app.notif_leave_approved_body',
            params: ['type' => 'سنوية'],
        );

        $n = Notification::firstOrFail();
        $arabic = $n->localizedTitle('ar');

        // الموظّف يبدّل لغته بعد وصول الإشعار
        $user->forceFill(['locale' => 'en'])->save();

        $this->assertSame(__('app.notif_leave_approved_title', [], 'en'), $n->localizedTitle('en'));
        $this->assertNotSame($arabic, $n->localizedTitle('en'));
    }

    public function test_an_old_notification_keeps_its_literal_text(): void
    {
        $user = $this->user('en');

        // إشعار كُتب قبل هذا التغيير: نصّ حرفي بلا مفتاح
        $old = Notification::create([
            'user_id' => $user->id,
            'title' => 'تم إكمال المهمة',
            'message' => "تم إكمال المهمة 'مراجعة العقد'",
            'type' => Notification::TYPE_INFO,
            'is_read' => false,
        ]);

        // يُعرض كما كُتب — لا يختفي ولا يصير مفتاحاً معروضاً
        $this->assertSame('تم إكمال المهمة', $old->localizedTitle('en'));
        $this->assertSame("تم إكمال المهمة 'مراجعة العقد'", $old->localizedMessage('ar'));
    }

    public function test_a_missing_key_falls_back_instead_of_showing_the_key(): void
    {
        $user = $this->user();

        $n = Notification::create([
            'user_id' => $user->id,
            'title' => 'نصّ احتياطي',
            'message' => 'رسالة احتياطية',
            'title_key' => 'app.key_that_does_not_exist',
            'message_key' => 'app.another_missing_key',
            'type' => Notification::TYPE_INFO,
            'is_read' => false,
        ]);

        $this->assertSame('نصّ احتياطي', $n->localizedTitle());
        $this->assertStringNotContainsString('app.', $n->localizedTitle());
    }

    public function test_the_language_choice_is_saved_on_the_user_not_only_the_session(): void
    {
        $user = $this->user('ar');

        $this->actingAs($user)->get('/lang/en');

        $this->assertSame('en', $user->fresh()->locale, 'الاختيار لم يُحفظ — سيضيع بالخروج.');
    }

    public function test_the_saved_choice_wins_over_a_stale_session(): void
    {
        $user = $this->user('en');

        // جلسة قديمة تحمل العربية، والمستخدم اختار الإنجليزية
        $this->withSession(['locale' => 'ar'])
            ->actingAs($user)
            ->get('/dashboard');

        $this->assertSame('en', app()->getLocale());
    }

    public function test_no_notification_text_is_hardcoded_any_more(): void
    {
        $offenders = [];

        foreach (['app/Http/Controllers', 'app/Services', 'app/Console/Commands'] as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($dir)));

            foreach ($it as $file) {
                if (!$file->isFile() || !str_ends_with($file->getFilename(), '.php')) {
                    continue;
                }

                if (str_contains(file_get_contents($file->getPathname()), 'Notification::create([')) {
                    $offenders[] = str_replace(base_path() . '/', '', $file->getPathname());
                }
            }
        }

        $this->assertSame([], $offenders,
            "إشعار يُكتب نصّه حرفياً — لن يتبع لغة قارئه:\n" . implode("\n", $offenders));
    }
}
