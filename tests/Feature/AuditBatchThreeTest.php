<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Document;
use App\Models\LegalCase;
use App\Models\Session as CourtSession;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Support\ClientPortal;
use App\Support\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * الدفعةُ الثالثة: تصعيدُ صلاحيةٍ عبر صندوق الوارد، وتسريباتُ بوّابة /my.
 */
class AuditBatchThreeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ═══ الرمزُ لا يُكتب في سجلّ الوارد ═══
     *
     * صندوقُ الوارد لا يفحص الصلاحيةَ لكلّ محادثةٍ على حدة: من يملك
     * whatsapp.view — موظّفُ استقبالٍ مثلاً — يفتح محادثةَ أيّ موكّل.
     * فكان يطلب الرمزَ من بوابة الدخول العامّة برقم الموكّل، ثمّ يقرؤه
     * من الوارد خلال دقائقه الخمس، ويدخل بوابتَه: تصعيدُ صلاحيةٍ كامل
     * بلا أثرٍ في سجلّ الموكّل. والرمزُ يبقى في النسخ الاحتياطية أبداً.
     */
    public function test_the_portal_code_is_never_written_into_the_inbox(): void
    {
        Setting::set(ClientPortal::KEY_ENABLED, '1', 'client_portal');
        config()->set('whatsapp.default', 'evolution');
        config()->set('whatsapp.evolution.base_url', 'http://127.0.0.1:18080');
        config()->set('whatsapp.evolution.api_key', 'test-key-0123456789');
        Setting::set(WhatsAppSettings::KEY_EVO_STATE, 'open', 'whatsapp');

        $sent = null;
        Http::fake(function ($request) use (&$sent) {
            if (str_contains($request->url(), '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'open']], 200);
            }
            $sent = $request->data();

            return Http::response(['key' => ['id' => 'OTP-1']], 200);
        });

        Client::create([
            'name' => 'موكّل', 'type' => 'individual',
            'national_id' => '13572468', 'phone' => '96891234567',
        ]);

        $this->post(route('client.access.otp'), ['phone' => '96891234567'])
            ->assertSessionHas('portal_notice');

        $row = WhatsAppMessage::latest('id')->firstOrFail();

        // لا رمزَ في الصفّ المحفوظ
        $this->assertDoesNotMatchRegularExpression('/\b\d{6}\b/u', (string) $row->body, 'الرمزُ مكتوبٌ في سجلّ الوارد');

        // والرمزُ الحقيقيُّ خرج إلى واتساب
        $this->assertNotNull($sent, 'لم تُرسل رسالةٌ أصلاً');
        $this->assertMatchesRegularExpression('/\b\d{6}\b/u', json_encode($sent, JSON_UNESCAPED_UNICODE), 'الرمزُ لم يصل الموكّل');
    }

    /**
     * وصفحةُ القضية في /my لا تعرض عناوينَ الأوراق الداخليّة.
     *
     * documents() صُحّحت وحدَها، وshowCase() بقيت تُحمّل المستندات بلا
     * تصفيةٍ أصلاً — الصفحتان في المتحكّم نفسِه.
     */
    public function test_the_my_case_page_hides_internal_document_titles(): void
    {
        $user = User::factory()->create(['role' => 'client', 'is_active' => true]);
        $staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);

        $client = Client::create([
            'name' => 'موكّل', 'type' => 'individual',
            'national_id' => '13572468', 'phone' => '96891234567', 'user_id' => $user->id,
        ]);

        $case = LegalCase::create([
            'case_number' => 'ق/1', 'office_case_number' => 'ق/1', 'title' => 'قضية', 'description' => 'و',
            'type' => 'مدني', 'court' => 'م', 'opponent' => 'خ', 'status' => 'active', 'priority' => 'medium',
            'client_id' => $client->id, 'created_by' => $staff->id, 'opened_at' => now(),
        ]);

        foreach ([
            ['مذكّرة داخلية — استراتيجية التسوية', 'all', false],
            ['تقرير الخبير الخاصّ', 'private', true],
            ['نسخة الحكم', 'all', true],
        ] as [$title, $access, $visible]) {
            Document::create([
                'case_id' => $case->id, 'uploaded_by' => $staff->id, 'title' => $title,
                'file_path' => 'documents/' . md5($title) . '.pdf', 'file_type' => 'pdf', 'file_size' => 10,
                'access_level' => $access, 'client_visible' => $visible,
            ]);
        }

        $html = $this->actingAs($user)->get(route('client.cases.show', $case))->assertOk()->getContent();

        $this->assertStringContainsString('نسخة الحكم', $html);
        $this->assertStringNotContainsString('مذكّرة داخلية', $html, 'عنوانُ ورقةٍ داخليّةٍ وصل الموكّل');
        $this->assertStringNotContainsString('تقرير الخبير الخاصّ', $html, 'عنوانُ ورقةٍ خاصّةٍ وصل الموكّل');
        $this->assertStringNotContainsString($staff->name, $html, 'اسمُ الموظّف الرافع في بوابة الموكّل');
    }

    /** وملاحظاتُ الجلسة الداخليّة لا تُعرض له. */
    public function test_internal_hearing_notes_stay_internal(): void
    {
        $user = User::factory()->create(['role' => 'client', 'is_active' => true]);
        $staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);

        $client = Client::create([
            'name' => 'موكّل', 'type' => 'individual',
            'national_id' => '13572468', 'phone' => '96891234567', 'user_id' => $user->id,
        ]);

        $case = LegalCase::create([
            'case_number' => 'ق/2', 'office_case_number' => 'ق/2', 'title' => 'قضية', 'description' => 'و',
            'type' => 'مدني', 'court' => 'م', 'opponent' => 'خ', 'status' => 'active', 'priority' => 'medium',
            'client_id' => $client->id, 'created_by' => $staff->id, 'opened_at' => now(),
        ]);

        CourtSession::create([
            'case_id' => $case->id, 'date' => now()->addDays(3), 'location' => 'قاعة 1', 'status' => 'upcoming',
            'notes' => 'القاضي يميل إلى الخصم — نحتاج شاهداً آخر',
            'report' => 'أُجّلت الجلسة إلى موعدٍ لاحق',
        ]);

        $html = $this->actingAs($user)->get(route('client.sessions'))->assertOk()->getContent();

        $this->assertStringNotContainsString('يميل إلى الخصم', $html, 'ملاحظةُ المحامي الداخليّة وصلت الموكّل');
        $this->assertStringContainsString('أُجّلت الجلسة', $html, 'ضاع الملخّصُ المكتوبُ للموكّل');
    }
}
