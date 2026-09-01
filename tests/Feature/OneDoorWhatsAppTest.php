<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\ClientEvents;
use App\Support\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * بابٌ واحدٌ لواتساب الموكّل — بقرار صاحب المنظومة، نصّاً:
 *
 * «ما أريد رداً آلياً ولا شيء. أريد تنبيه الموكّل بالواتساب إذا
 * حُدّثت قضيته أو صار فيها أي شيء جديد أو تغيّر موعد أو فاتورة —
 * ويزور الموقع. ما أريد غير هذا الشيء فقط».
 *
 * فالإشعارُ برابط البوابة هو الطريقُ الوحيد، والهجرةُ تشغّله لكل
 * مكتب، والردُّ الآلي والأبوابُ القديمة الثلاثة مغلقةٌ بلا نموذجٍ
 * يعيد فتحها.
 */
class OneDoorWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    private function developer(): User
    {
        return User::factory()->create(['role' => 'developer', 'is_active' => true]);
    }

    private function runMigration(): void
    {
        $migration = require database_path('migrations/2026_09_01_100007_one_door_for_client_whatsapp.php');
        $migration->up();
    }

    /** الهجرةُ تشغّل البابَ الواحد وتغلق ما سواه — على كل مكتب. */
    public function test_the_migration_opens_the_one_door_and_closes_the_rest(): void
    {
        Setting::set('wa_ai_reply', '1', 'whatsapp');
        Setting::set('wa_notify_sessions', '1', 'whatsapp');
        $this->assertFalse(ClientEvents::masterEnabled());

        $this->runMigration();

        $this->assertTrue(ClientEvents::masterEnabled(), 'الهجرةُ لم تشغّل الإشعارات');
        $this->assertFalse(WhatsAppSettings::flag(WhatsAppSettings::KEY_AI_REPLY), 'الردُّ الآلي بقي حيّاً');
        $this->assertFalse(WhatsAppSettings::flag(WhatsAppSettings::KEY_NOTIFY_SESSIONS), 'البابُ القديم بقي مفتوحاً');
    }

    /** والشاشةُ لا تعرض المغلقَ أصلاً — فلا يُعاد فتحُه سهواً. */
    public function test_the_settings_screen_no_longer_offers_the_closed_doors(): void
    {
        $html = $this->actingAs($this->developer())
            ->get(route('settings.index'))->assertOk()->getContent();

        foreach (['wa_ai_reply', 'wa_notify_sessions', 'wa_notify_invoices', 'wa_notify_case_updates'] as $field) {
            $this->assertStringNotContainsString('name="' . $field . '"', $html,
                'خانةُ بابٍ مغلقٍ ما زالت تُعرض: ' . $field);
        }
    }

    /** ونموذجٌ مصنوعٌ باليد لا يعيد فتحَ الردّ الآلي. */
    public function test_a_hand_crafted_form_cannot_reopen_the_auto_reply(): void
    {
        $this->runMigration();

        $this->actingAs($this->developer())->post(route('settings.whatsapp.update'), [
            'wa_ai_reply' => '1',
            'wa_notify_sessions' => '1',
        ])->assertRedirect();

        $this->assertFalse(WhatsAppSettings::flag(WhatsAppSettings::KEY_AI_REPLY),
            'نموذجٌ أعاد فتحَ الردّ الآلي');
        $this->assertFalse(WhatsAppSettings::flag(WhatsAppSettings::KEY_NOTIFY_SESSIONS));
    }

    /** وتذكيرُ الجلسات يُحكم من الباب الواحد لا من العلَم القديم. */
    public function test_the_session_reminder_obeys_the_one_door(): void
    {
        $this->runMigration();

        // العلَمُ القديم مفتوحٌ عمداً — ويجب ألّا يُسمَع له
        Setting::set('wa_notify_sessions', '1', 'whatsapp');
        ClientEvents::setEnabled(ClientEvents::SESSION_REMINDER, false);

        $this->artisan('whatsapp:session-reminders')
            ->expectsOutputToContain('تذكير قبل الجلسة')
            ->assertSuccessful();
    }
}
