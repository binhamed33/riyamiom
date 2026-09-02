<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientNotification;
use App\Models\LegalCase;
use App\Models\Setting;
use App\Models\User;
use App\Support\AppointmentSlots;
use App\Support\ClientEvents;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * حجزُ المواعيد: فُسحةٌ شاغرةٌ، ورسالةٌ تصل صاحبَها.
 *
 * ═══ ما تحرسه ═══
 *
 * ١) الحجزُ يُشعر الموكّل: واتساباً (من الباب الواحد) وبريداً.
 * ٢) التعارضُ يُرفض عند الحفظ لا عند العرض — شاشتان تريان الفُسحةَ
 *    نفسَها شاغرة، فالحَكَمُ عند الكتابة.
 * ٣) الفُسَحُ تحترم أوقاتَ الدوام وأيّامَه، والمحجوزُ يسقط منها.
 * ٤) تغييرُ الوقت يُخبر، وتصحيحُ ملاحظةٍ لا يُخبر — الرسالةُ حين
 *    يتغيّر ما يعني الموكّل وحده.
 */
class AppointmentsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->client = Client::create([
            'name' => 'راشد بن سعيد الحبسي',
            'type' => 'individual',
            'phone' => '96891234567',
            'national_id' => '11834562',
            'email' => 'rashid@example.om',
        ]);

        // الباب الواحد مفتوح: قاعدةُ الاختبارات تُطفئه، وهذه الحزمة
        // تفحص الإشعار نفسَه فتشغّله صراحةً
        Setting::set(ClientEvents::KEY_MASTER, '1', ClientEvents::GROUP);
        Setting::set(AppointmentSlots::KEY_DAYS, '0,1,2,3,4', 'appointments');
        Setting::set(AppointmentSlots::KEY_START, '08:00', 'appointments');
        Setting::set(AppointmentSlots::KEY_END, '12:00', 'appointments');
        Setting::set(AppointmentSlots::KEY_SLOT, '30', 'appointments');

        // بريدٌ «مضبوط» بناقلٍ مصفوفة: OfficeMailer يرفض مكتباً بلا
        // إعدادات SMTP — وهو الصواب — فلولا هذا لبدا أنّ البريد لا
        // يُرسَل بينما العلّةُ في بيئة الاختبار وحدها. ولا يغادر بايت.
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp' => ['transport' => 'array'],
            'mail.from.address' => 'office@example.om',
            'mail.mailers.smtp.host' => 'smtp.example.om',
            'mail.mailers.smtp.username' => 'office@example.om',
            'mail.mailers.smtp.password' => 'sixteenlowercase',
        ]);
    }

    /** أوّلُ يومِ عملٍ قادم — فلا يتعلّق الاختبارُ بيوم تشغيله. */
    private function nextWorkday(): \Illuminate\Support\Carbon
    {
        $day = now()->addDay()->startOfDay();

        while (!AppointmentSlots::isWorkday($day)) {
            $day->addDay();
        }

        return $day;
    }

    public function test_booking_notifies_the_client_on_whatsapp_and_email(): void
    {
        Mail::fake();
        $day = $this->nextWorkday();

        $this->actingAs($this->admin)->post(route('appointments.store'), [
            'client_id' => $this->client->id,
            'title' => 'استشارة أولى',
            'date' => $day->toDateString(),
            'time' => '09:00',
            'minutes' => 30,
            'location' => 'مكتب المحاماة',
        ])->assertRedirect();

        $appointment = Appointment::firstOrFail();
        $this->assertSame('scheduled', $appointment->status);
        $this->assertSame('09:00', $appointment->starts_at->format('H:i'));

        // واتساب: صفٌّ في البابِ الواحد بنصٍّ يحمل الوقت
        $notification = ClientNotification::where('type', ClientEvents::APPOINTMENT_NEW)->firstOrFail();
        $this->assertSame($this->client->id, $notification->client_id);
        $this->assertStringContainsString('استشارة أولى', (string) $notification->body);
        $this->assertSame('appointments', $notification->target);

        // بريد: بالنصّ نفسِه. و`assertQueued` لا `assertSent`: البريد
        // يمرّ من الطابور كي لا ينتظر الحفظُ مصافحةَ SMTP.
        Mail::assertQueued(\App\Mail\ClientEventMail::class, function ($mail) {
            return $mail->kind === \App\Mail\MailKind::AppointmentNotice
                && str_contains($mail->bodyText, 'استشارة أولى');
        });
    }

    /** الوقتُ المحجوز لدى الموظّف نفسِه يُرفض — ولو تداخل جزئياً. */
    public function test_an_overlapping_slot_is_refused_at_save(): void
    {
        $day = $this->nextWorkday();

        Appointment::create([
            'client_id' => $this->client->id,
            'user_id' => $this->admin->id,
            'title' => 'موعد قائم',
            'starts_at' => $day->copy()->setTime(9, 0),
            'minutes' => 60,
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        // يبدأ في التاسعة والنصف — داخلَ الساعة المحجوزة
        $this->actingAs($this->admin)->post(route('appointments.store'), [
            'client_id' => $this->client->id,
            'user_id' => $this->admin->id,
            'title' => 'موعد متعارض',
            'date' => $day->toDateString(),
            'time' => '09:30',
            'minutes' => 30,
        ])->assertSessionHas('error');

        $this->assertSame(1, Appointment::count(), 'حُجز موعدان على الموظّف نفسِه في الوقت نفسِه');

        // وموظّفٌ آخر في الوقت نفسِه لا يتعارض
        $other = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);

        $this->actingAs($this->admin)->post(route('appointments.store'), [
            'client_id' => $this->client->id,
            'user_id' => $other->id,
            'title' => 'موعد مع زميل',
            'date' => $day->toDateString(),
            'time' => '09:30',
            'minutes' => 30,
        ])->assertRedirect();

        $this->assertSame(2, Appointment::count());
    }

    /** الفُسَح: من أوقات الدوام، والمحجوزُ يسقط منها. */
    public function test_slots_follow_working_hours_and_drop_the_taken(): void
    {
        $day = $this->nextWorkday();

        Appointment::create([
            'client_id' => $this->client->id,
            'user_id' => $this->admin->id,
            'title' => 'محجوز',
            'starts_at' => $day->copy()->setTime(9, 0),
            'minutes' => 30,
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('appointments.slots', ['day' => $day->toDateString(), 'user_id' => $this->admin->id]))
            ->assertOk();

        $slots = collect($response->json('slots'));

        $this->assertTrue($response->json('workday'));
        $this->assertSame(8, $slots->count(), 'من الثامنة إلى الثانية عشرة بفُسحة نصف ساعة = ثمانٍ');
        $this->assertFalse($slots->firstWhere('time', '09:00')['free'], 'المحجوزُ عُرض شاغراً');
        $this->assertTrue($slots->firstWhere('time', '10:00')['free']);

        // يومُ عطلةٍ لا فُسَحَ فيه
        Setting::set(AppointmentSlots::KEY_DAYS, '1', 'appointments');
        $off = $day->copy();
        while ($off->dayOfWeek === 1) {
            $off->addDay();
        }

        $this->actingAs($this->admin)
            ->getJson(route('appointments.slots', ['day' => $off->toDateString()]))
            ->assertOk()
            ->assertJsonPath('workday', false)
            ->assertJsonPath('slots', []);
    }

    /** تغييرُ الوقت يُخبر الموكّل، وتصحيحُ ملاحظةٍ لا يُخبره. */
    public function test_only_a_real_change_reaches_the_client(): void
    {
        $day = $this->nextWorkday();

        $appointment = Appointment::create([
            'client_id' => $this->client->id,
            'user_id' => $this->admin->id,
            'title' => 'مراجعة مستندات',
            'starts_at' => $day->copy()->setTime(9, 0),
            'minutes' => 30,
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        $base = [
            'client_id' => $this->client->id,
            'user_id' => $this->admin->id,
            'title' => 'مراجعة مستندات',
            'date' => $day->toDateString(),
            'time' => '09:00',
            'minutes' => 30,
        ];

        // ملاحظةٌ داخلية: لا رسالة
        $this->actingAs($this->admin)
            ->put(route('appointments.update', $appointment), $base + ['notes' => 'يحضر ومعه الأصول'])
            ->assertRedirect();

        $this->assertSame(0, ClientNotification::where('type', ClientEvents::APPOINTMENT_MOVED)->count());

        // تغييرُ الوقت: رسالة
        $this->actingAs($this->admin)
            ->put(route('appointments.update', $appointment), array_merge($base, ['time' => '10:30']))
            ->assertRedirect();

        $this->assertSame(1, ClientNotification::where('type', ClientEvents::APPOINTMENT_MOVED)->count());

        // الإلغاء: رسالة
        $this->actingAs($this->admin)
            ->patch(route('appointments.status', $appointment), ['status' => 'cancelled'])
            ->assertRedirect();

        $this->assertSame(1, ClientNotification::where('type', ClientEvents::APPOINTMENT_CANCELLED)->count());
    }

    /** التذكيرُ يُرسَل مرّةً واحدةً مهما تكرّر الأمرُ في الساعات. */
    public function test_the_reminder_fires_once_per_appointment(): void
    {
        Setting::set(AppointmentSlots::KEY_REMIND, '24', 'appointments');

        $appointment = Appointment::create([
            'client_id' => $this->client->id,
            'title' => 'موعد الغد',
            'starts_at' => now()->addHours(3),
            'minutes' => 30,
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        $this->artisan('appointments:remind')->assertSuccessful();
        $this->assertSame(1, ClientNotification::where('type', ClientEvents::APPOINTMENT_REMINDER)->count());
        $this->assertNotNull($appointment->fresh()->reminded_at);

        // ساعةٌ أخرى: لا رسالةَ ثانية
        $this->artisan('appointments:remind')->assertSuccessful();
        $this->assertSame(1, ClientNotification::where('type', ClientEvents::APPOINTMENT_REMINDER)->count());
    }

    /** الموكّل يرى موعده في بوابته — ولا يرى موعدَ غيره. */
    public function test_the_portal_shows_only_the_signed_in_clients_appointments(): void
    {
        Setting::set(\App\Support\ClientPortal::KEY_ENABLED, '1', 'client_portal');

        $other = Client::create([
            'name' => 'موكّل آخر',
            'type' => 'individual',
            'phone' => '96899999999',
            'national_id' => '99999999',
        ]);

        Appointment::create([
            'client_id' => $this->client->id,
            'title' => 'موعدي أنا',
            'starts_at' => now()->addDays(2)->setTime(9, 0),
            'minutes' => 30,
            'status' => Appointment::STATUS_SCHEDULED,
        ]);
        Appointment::create([
            'client_id' => $other->id,
            'title' => 'موعد الغريب',
            'starts_at' => now()->addDays(2)->setTime(11, 0),
            'minutes' => 30,
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        $this->withSession([
            \App\Services\ClientPortal\ClientAuthenticator::SESSION_CLIENT => $this->client->id,
            \App\Services\ClientPortal\ClientAuthenticator::SESSION_AT => now()->timestamp,
        ]);

        $html = $this->get(route('client.portal.home'))->getContent();

        $this->assertStringContainsString('موعدي أنا', $html);
        $this->assertStringNotContainsString('موعد الغريب', $html, 'الموكّل رأى موعدَ غيره');
    }

    /** موعدٌ بلا قضية جائز — استشارةُ زائرٍ أوّلَ مرّة. */
    public function test_an_appointment_needs_no_case(): void
    {
        $day = $this->nextWorkday();

        $this->actingAs($this->admin)->post(route('appointments.store'), [
            'client_id' => $this->client->id,
            'title' => 'استشارة بلا قضية',
            'date' => $day->toDateString(),
            'time' => '08:30',
        ])->assertRedirect();

        $this->assertNull(Appointment::firstOrFail()->case_id);
    }

    /**
     * موعدٌ مع شخصٍ لا ملفَّ له — وهو أكثرُ المواعيد الأولى.
     *
     * إلزامُ الموظّف بإنشاء موكّلٍ كاملٍ قبل أن يكتب موعداً يعني
     * سجلَّ موكّلين ممتلئاً بمن لم يوكّل أحداً، أو مواعيدَ في ورقةٍ
     * على الطاولة.
     */
    public function test_an_appointment_with_a_walk_in_person(): void
    {
        // رقمُ المكتب مربوطٌ — بلا ربطٍ لا رسالةَ تُكتب أصلاً وهو الصواب
        config()->set('whatsapp.default', 'evolution');
        config()->set('whatsapp.evolution.base_url', 'http://127.0.0.1:18080');
        config()->set('whatsapp.evolution.api_key', 'test-key-0123456789');
        Setting::set(\App\Support\WhatsAppSettings::KEY_EVO_STATE, 'open', 'whatsapp');
        Setting::set(\App\Support\WhatsAppSettings::KEY_CONNECTED_AT, now()->toIso8601String(), 'whatsapp');

        $day = $this->nextWorkday();

        $this->actingAs($this->admin)->post(route('appointments.store'), [
            'guest_name' => 'سالم بن علي',
            'guest_phone' => '96899887766',
            'title' => 'استشارة أولى',
            'date' => $day->toDateString(),
            'time' => '09:00',
        ])->assertRedirect();

        $appointment = Appointment::firstOrFail();

        $this->assertNull($appointment->client_id, 'أُنشئ موكّلٌ لم يطلبه أحد');
        $this->assertSame('سالم بن علي', $appointment->personName());
        $this->assertSame('96899887766', $appointment->personPhone());
        $this->assertTrue($appointment->isGuest());

        // ورسالتُه تُكتب في دفتر المكتب وتُدفع للطابور — لا بوابةَ له
        $message = \App\Models\WhatsAppMessage::latest('id')->first();
        $this->assertNotNull($message, 'لم تُكتب رسالةُ تأكيدٍ للشخص');
        $this->assertStringContainsString('استشارة أولى', (string) $message->body);
        $this->assertNotNull($message->sent_by, 'الرسالةُ لم تُنسب إلى من حجز — فتُعامَل بثّاً آلياً');
        $this->assertSame(0, ClientNotification::count(), 'قُيّد إشعارُ بوابةٍ لمن لا بوابةَ له');
    }

    /** ولا موعدَ بلا صاحب: لا موكّلٌ ولا اسمٌ ورقم. */
    public function test_an_appointment_needs_someone(): void
    {
        $day = $this->nextWorkday();

        $this->actingAs($this->admin)->post(route('appointments.store'), [
            'title' => 'موعد بلا صاحب',
            'date' => $day->toDateString(),
            'time' => '09:00',
        ])->assertSessionHasErrors('client_id');

        $this->assertSame(0, Appointment::count());
    }

    /** التقويم يُري الأسبوعَ ونسبةَ ازدحام كلِّ يوم. */
    public function test_the_week_calendar_shows_the_load(): void
    {
        $day = $this->nextWorkday();

        Appointment::create([
            'client_id' => $this->client->id,
            'user_id' => $this->admin->id,
            'title' => 'موعد في التقويم',
            'starts_at' => $day->copy()->setTime(9, 0),
            'minutes' => 30,
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        $html = $this->actingAs($this->admin)
            ->get(route('appointments.index', ['view' => 'week', 'day' => $day->toDateString()]))
            ->assertOk()->getContent();

        $this->assertStringContainsString('تقويم الأسبوع', $html);
        $this->assertStringContainsString('09:00', $html);
        $this->assertStringContainsString('راشد بن سعيد الحبسي', $html);
    }

    /**
     * «مضى» ليس «محجوزاً» — والفرقُ يمنع شاشةً تبدو معطّلة.
     *
     * يومٌ انقضى دوامُه كان يُعرض كلُّه مشطوباً كأنّ المكتبَ محجوزٌ
     * بالكامل، فيظنّ الموظّفُ العطلَ في النظام. الحالةُ تُسمّى الآن،
     * ومعها أقربُ يومٍ فيه فُسحة.
     */
    public function test_a_finished_day_says_so_and_points_to_the_next_open_one(): void
    {
        // كلُّ أيّام الأسبوع عملٌ حتى لا يتعلّق الاختبارُ بيوم تشغيله
        Setting::set(AppointmentSlots::KEY_DAYS, '0,1,2,3,4,5,6', 'appointments');

        // الساعةُ بعد نهاية الدوام: كلُّ فُسَح اليوم مضت
        $this->travelTo(now()->setTime(20, 0));

        $response = $this->actingAs($this->admin)
            ->getJson(route('appointments.slots', ['day' => now()->toDateString()]))
            ->assertOk();

        $this->assertFalse($response->json('has_free'), 'يومٌ انقضى دوامُه عُرض وفيه فُسحة');
        $this->assertSame(
            ['past'],
            array_values(array_unique(array_column($response->json('slots'), 'state'))),
            'حالةُ الفُسحة الماضية لم تُسمَّ «مضى»',
        );

        // وأقربُ يومٍ فيه فُسحةٌ يُعرض وجهةً بديلة
        $next = $response->json('next_open');
        $this->assertNotNull($next, 'لا وجهةَ بديلةً ليومٍ انقضى');
        $this->assertSame(now()->addDay()->toDateString(), $next['date']);
        $this->assertSame('08:00', $next['time']);
    }

    /** والمحجوزُ يُسمّى «محجوزاً» لا «مضى». */
    public function test_a_taken_slot_is_named_busy(): void
    {
        $day = $this->nextWorkday();

        Appointment::create([
            'client_id' => $this->client->id,
            'user_id' => $this->admin->id,
            'title' => 'محجوز',
            'starts_at' => $day->copy()->setTime(9, 0),
            'minutes' => 30,
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        $slots = collect($this->actingAs($this->admin)
            ->getJson(route('appointments.slots', ['day' => $day->toDateString(), 'user_id' => $this->admin->id]))
            ->assertOk()->json('slots'));

        $this->assertSame('busy', $slots->firstWhere('time', '09:00')['state']);
        $this->assertSame('free', $slots->firstWhere('time', '10:00')['state']);
    }
}
