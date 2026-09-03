<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Setting;
use App\Models\User;
use App\Support\AppointmentSlots;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * الشاشةُ تعرض ساعاتِ اليوم كلَّها — والمحجوزُ منها مقفلٌ في أيّها.
 *
 * ═══ ما كان ═══
 *
 * خارجَ أيّام العمل لا وقتَ يُعرض البتّة، وداخلَ اليوم لا شيءَ قبل
 * الثامنة ولا بعد الرابعة. والشاشةُ تقول «اكتب الوقتَ يدوياً» — فيكتبه
 * الموظّفُ بلا أن يرى ما حُجز فيه، ويكتشف التعارضَ عند الحفظ لا قبله.
 * وأسوأُ منه: موعدٌ في السابعة مساءً لا يظهر لأحدٍ بعده، فيُحجز فوقه.
 *
 * ═══ وما تحرسه ═══
 *
 * ١) اليومُ كلُّه يُعرض: جمعةً كان أو ساعةً خارج الدوام.
 * ٢) والمحجوزُ مقفلٌ في كلّ ساعةٍ من اليوم لا في ساعات الدوام وحدَها.
 * ٣) وخارجُ الدوام يُعلَّم «outside» ويبقى قابلاً للحجز — المكتبُ يعمل
 *    خارجَ دوامه أحياناً، وليس من حقّ الشاشة أن تمنعه.
 * ٤) ولا يُقترح وقتٌ خارج الدوام في «أقرب موعد».
 * ٥) والجدولُ الأسبوعيُّ يحسب الامتلاءَ من فُسَح الدوام وحدَها.
 */
class AppointmentWholeDayTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);

        // الأحد إلى الخميس، ٨ ⇐ ١٦ — الافتراض
        Setting::set(AppointmentSlots::KEY_DAYS, '0,1,2,3,4', 'appointments');
        Setting::set(AppointmentSlots::KEY_START, '08:00', 'appointments');
        Setting::set(AppointmentSlots::KEY_END, '16:00', 'appointments');
        Setting::set(AppointmentSlots::KEY_SLOT, '30', 'appointments');
    }

    /** يومُ عملٍ قادمٌ في الأسبوع المقبل — بعيدٌ عن «مضى». */
    private function workday(): Carbon
    {
        return now()->addWeek()->startOfWeek(\Carbon\CarbonInterface::SUNDAY);
    }

    private function friday(): Carbon
    {
        return $this->workday()->copy()->addDays(5);
    }

    private function book(Carbon $at, int $minutes = 30): Appointment
    {
        return Appointment::create([
            'title' => 'موعد',
            'guest_name' => 'ضيف',
            'guest_phone' => '96890000000',
            'starts_at' => $at,
            'minutes' => $minutes,
            'status' => Appointment::STATUS_SCHEDULED,
            'user_id' => $this->staff->id,
            'created_by' => $this->staff->id,
        ]);
    }

    // ───────────────────────────────────────── المحرّك

    /** اليومُ كلُّه ٤٨ فُسحةً — جمعةً كان أو يومَ عمل. */
    public function test_the_whole_day_is_offered_even_outside_working_days(): void
    {
        foreach ([$this->workday(), $this->friday()] as $day) {
            $slots = AppointmentSlots::forDay($day, $this->staff->id, null, wholeDay: true);

            $this->assertCount(48, $slots, 'اليومُ لا يُعرض كاملاً: ' . $day->toDateString());
            $this->assertSame('00:00', $slots[0]['time']);
            $this->assertSame('23:30', $slots[47]['time']);
        }
    }

    /** والافتراضُ لم يتغيّر: بلا طلبٍ صريح تبقى ساعاتُ الدوام وحدَها. */
    public function test_the_default_still_returns_office_hours_only(): void
    {
        $this->assertCount(16, AppointmentSlots::forDay($this->workday(), $this->staff->id));
        $this->assertSame([], AppointmentSlots::forDay($this->friday(), $this->staff->id));
    }

    /**
     * ═══ لبُّ الطلب ═══
     *
     * موعدٌ في السابعة مساءً — خارج الدوام — يُقفل ساعتَه ولا يُحجز
     * فوقه. وقبله كانت تلك الساعةُ لا تُعرض أصلاً فتُحجز مرّةً بعد أخرى.
     */
    public function test_a_booked_hour_is_locked_at_any_time_of_day(): void
    {
        $day = $this->workday();
        $this->book($day->copy()->setTime(19, 0));

        $slots = collect(AppointmentSlots::forDay($day, $this->staff->id, null, wholeDay: true))
            ->keyBy('time');

        $this->assertSame('busy', $slots['19:00']['state'], 'موعدٌ خارج الدوام لم يُقفل ساعتَه');
        $this->assertFalse($slots['19:00']['free']);

        // وجارتُها خارجَ الدوام تبقى قابلةً للحجز
        $this->assertSame('outside', $slots['19:30']['state']);
        $this->assertTrue($slots['19:30']['free']);
    }

    /** ويومَ الجمعة كذلك: المحجوزُ مقفلٌ وغيرُه يُختار. */
    public function test_a_friday_booking_locks_its_slot_too(): void
    {
        $friday = $this->friday();
        $this->book($friday->copy()->setTime(10, 0));

        $slots = collect(AppointmentSlots::forDay($friday, $this->staff->id, null, wholeDay: true))
            ->keyBy('time');

        $this->assertSame('busy', $slots['10:00']['state']);
        $this->assertSame('outside', $slots['10:30']['state'], 'الجمعةُ كلُّها خارج الدوام');
        $this->assertTrue($slots['10:30']['free'], 'الجمعةُ لا تُحجز أصلاً');
    }

    /** وساعاتُ الدوام تبقى «free» لا «outside». */
    public function test_office_hours_keep_their_own_state(): void
    {
        $slots = collect(AppointmentSlots::forDay($this->workday(), $this->staff->id, null, wholeDay: true))
            ->keyBy('time');

        $this->assertSame('outside', $slots['07:30']['state'], 'قبل الدوام');
        $this->assertSame('free', $slots['08:00']['state'], 'أوّلُ الدوام');
        $this->assertSame('free', $slots['15:30']['state'], 'آخرُ فُسحةٍ تنتهي بنهايته');
        $this->assertSame('outside', $slots['16:00']['state'], 'بعد الدوام');
    }

    /** و«أقربُ موعد» لا يقترح الثانيةَ فجراً. */
    public function test_the_next_open_suggestion_stays_inside_office_hours(): void
    {
        $next = AppointmentSlots::nextOpenDay($this->staff->id, $this->workday());

        $this->assertNotNull($next);
        $this->assertGreaterThanOrEqual('08:00', $next['time']);
        $this->assertLessThan('16:00', $next['time']);
        $this->assertContains($next['date']->dayOfWeek, AppointmentSlots::days(), 'اقتُرح يومُ عطلة');
    }

    // ───────────────────────────────────────── المسار

    /** والمسارُ يردّ اليومَ كاملاً مع حالة كلّ فُسحة. */
    public function test_the_endpoint_returns_the_whole_day(): void
    {
        $friday = $this->friday();
        $this->book($friday->copy()->setTime(21, 0));

        $json = $this->actingAs($this->staff)->getJson(route('appointments.slots', [
            'day' => $friday->toDateString(),
            'user_id' => $this->staff->id,
        ]))->assertOk()->json();

        $this->assertFalse($json['workday']);
        $this->assertCount(48, $json['slots'], 'الجمعةُ رجعت فارغة');
        $this->assertTrue($json['has_bookable'], 'لا وقتَ يُنقر في يومٍ خارج الدوام');
        $this->assertFalse($json['has_free'], 'يومٌ خارج الدوام لا فُسحةَ دوامٍ فيه');
        $this->assertSame('08:00 – 16:00', $json['office_hours']);

        $byTime = collect($json['slots'])->keyBy('time');
        $this->assertSame('busy', $byTime['21:00']['state']);
        $this->assertFalse($byTime['21:00']['free']);
    }

    /**
     * والحفظُ يرفض المحجوزَ خارجَ الدوام كما يرفضه داخلَه.
     *
     * الرفضُ رسالةٌ مُومضة لا خطأُ تحقّق (المتحكّم يردّ back()->with('error'))
     * — والمهمُّ أنّ الصفَّ الثاني لا يُكتب.
     */
    public function test_saving_over_a_booked_out_of_hours_slot_is_refused(): void
    {
        $day = $this->workday();
        $this->book($day->copy()->setTime(20, 0));

        $this->actingAs($this->staff)->post(route('appointments.store'), [
            'title' => 'موعد ثانٍ',
            'guest_name' => 'ضيف آخر',
            'guest_phone' => '96891111111',
            'date' => $day->toDateString(),
            'time' => '20:00',
            'minutes' => 30,
            'user_id' => $this->staff->id,
        ])->assertSessionHas('error');

        $this->assertSame(1, Appointment::count(), 'حُجز موعدان في الساعة نفسِها');

        // والتداخلُ لا التطابق: نصفُ ساعةٍ تبدأ داخل الموعد تُرفض أيضاً
        $this->actingAs($this->staff)->post(route('appointments.store'), [
            'title' => 'موعد ثالث',
            'guest_name' => 'ضيف ثالث',
            'guest_phone' => '96892222222',
            'date' => $day->toDateString(),
            'time' => '20:15',
            'minutes' => 30,
            'user_id' => $this->staff->id,
        ])->assertSessionHas('error');

        $this->assertSame(1, Appointment::count(), 'حُجز موعدٌ متداخلٌ مع موعد');

        // وموظّفٌ آخر في الساعة نفسِها يمرّ — التعارضُ لكلّ موظّفٍ على حدة
        $other = User::factory()->create(['role' => 'staff', 'is_active' => true]);

        $this->actingAs($this->staff)->post(route('appointments.store'), [
            'title' => 'موعد لموظّفٍ آخر',
            'guest_name' => 'ضيف رابع',
            'guest_phone' => '96893333333',
            'date' => $day->toDateString(),
            'time' => '20:00',
            'minutes' => 30,
            'user_id' => $other->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, Appointment::count(), 'مُنع موظّفٌ آخر بلا سبب');
    }
}
