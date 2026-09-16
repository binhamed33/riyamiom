<?php

namespace Tests\Feature;

use App\Models\HrAttendance;
use App\Models\Setting;
use App\Models\User;
use App\Support\AttendanceGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * الانصراف يُسجَّل بزرّ الانصراف وحده — لا بالخروج من النظام ولا
 * باستنتاجٍ من غياب النشاط.
 *
 * ═══ اقتراحٌ من محامٍ يستعمل النظام ═══
 *
 * «ننشغل بالكتابات ومقابلة الموكّلين ولسنا متفرّغين لتحديث المنظومة
 * باستمرار» — فآخرُ نقرةٍ له الساعة ١١:٢٠ كانت تُكتب وقتَ انصرافه
 * وهو في مكتبه إلى العصر. وقتٌ مخترَعٌ في كشف دوامٍ أسوأ من خانةٍ
 * فارغةٍ تقول الصدق: «لم يُسجَّل».
 *
 * فصار الإقفال الليليّ خياراً معطَّلاً افتراضاً، والانصرافُ لا يكتبه
 * إلا الزرّ.
 */
class AttendanceOnlyByButtonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // ساعةٌ مجمَّدة: الحزمةُ كانت خضراءَ بعد السادسة مساءً فقط — أوقاتُ «16:00» المزروعة
        // تقع في المستقبل صباحاً أو داخل نافذة الخمول عصراً
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-16 21:00:00', 'Asia/Muscat'));
    }

    private function staff(): User
    {
        return User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
    }

    private function openRecord(User $user, string $checkIn = '08:00'): HrAttendance
    {
        return HrAttendance::create([
            'user_id' => $user->id,
            'work_date' => now()->toDateString(),
            'check_in_at' => now()->setTimeFromTimeString($checkIn),
            'status' => 'present',
            'source' => 'login',
        ]);
    }

    /**
     * خروجُ الخمول التلقائي يُغلق الجلسة ولا يُنهي يومَ العمل.
     *
     * نافذةُ «هل ما زلت هنا؟» تُسلّم نموذجَ الخروج نفسه بعد ١١ دقيقة
     * خمول — فكان زرُّ الخروج يُضغط نيابةً عن المحامي وهو في مكتبه،
     * ويُسجَّل انصرافُه: الشكوى التي أُغلق بابُها عائدةً من بابٍ خلفي.
     */
    public function test_idle_auto_logout_ends_the_session_but_not_the_work_day(): void
    {
        $user = $this->staff();
        $record = $this->openRecord($user);

        $this->actingAs($user)->post(route('logout'), ['auto' => '1']);

        $this->assertGuest();
        $this->assertNull($record->fresh()->check_out_at, 'خمولُ الشاشة سجّل انصرافاً');
    }

    /**
     * وزرُّ الخروج الصريح يُغلق الجلسةَ ولا يُنهي يومَ العمل هو الآخر.
     *
     * ═══ الشكوى ═══
     *
     * «تسجيل الخروج ليس فقط يخرجنا من النظام، بل يسوّي مغادرة» — فمن
     * خرج ظهراً ليقفل جهازَه في الاستراحة، أو ليدخل من جهازٍ آخر، وُجد
     * يومُه «مكتملاً» وضاعت فترتُه المسائيّة من كشف الشهر. الانصرافُ
     * لزرّه وحده.
     */
    public function test_the_logout_button_ends_the_session_not_the_work_day(): void
    {
        $user = $this->staff();
        $record = $this->openRecord($user);

        $this->actingAs($user)->post(route('logout'));

        $this->assertGuest();
        $this->assertNull($record->fresh()->check_out_at, 'زرُّ الخروج ما زال يسجّل انصرافاً');
        $this->assertSame('present', $record->fresh()->status);
    }

    /**
     * وواجهةُ الخمول موصولة فعلاً: النبضة تتوقف عند موت الجلسة بدل قرع
     * الخادم برمزٍ ميت كلَّ ١٠ ثوانٍ، وزرُّ «تسجيل خروج» في النافذة يُرسل
     * النموذجَ فعلاً.
     *
     * كان الزرُّ يمرّر حدثَ النقر إلى doLogout فيُقرأ «الجلسة ميتة»:
     * تحويلٌ إلى صفحة الدخول بلا خروج، والجلسةُ الحيّة تعيده إلى لوحته.
     */
    public function test_the_idle_ui_declares_itself_and_stops_on_a_dead_session(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString('doLogout(true)', $layout, 'موت الجلسة لا يوقف النبضة');
        $this->assertStringContainsString('window.location.replace', $layout,
            'الجلسة الميتة تُرسَل نموذجاً برمز CSRF ميت بدل صفحة الدخول');
        $this->assertStringContainsString("outBtn.addEventListener('click', function () { doLogout(false); })", $layout,
            'زرُّ الخروج في نافذة الخمول يمرّر حدثَ النقر فلا يخرج أحد');
        $this->assertStringNotContainsString('name="auto"', $layout,
            'علامةُ auto بقيت — والخروجُ كلُّه لم يعد يسجّل انصرافاً');
    }

    /** الافتراض: لا يُخترع وقتُ انصرافٍ من آخر نشاط. */
    public function test_inactivity_never_writes_a_checkout_by_default(): void
    {
        $user = $this->staff();
        $record = $this->openRecord($user);
        DB::table('sessions')->insert([
            'id' => 'sess-' . uniqid(), 'user_id' => $user->id,
            'ip_address' => '127.0.0.1', 'user_agent' => 't', 'payload' => '',
            'last_activity' => now()->setTimeFromTimeString('11:20')->timestamp,
        ]);

        DB::table('users')->where('id', $user->id)->update(['last_seen_at' => now()->setTimeFromTimeString('11:20')]);

        $this->assertSame(0, AttendanceGuard::closeStaleRecords());
        $this->assertNull($record->fresh()->check_out_at, 'اختُرع وقتُ انصرافٍ من آخر نقرة');
    }

    /** والأمر الليليّ المجدول يمتنع كذلك — ويقولها. */
    public function test_the_scheduled_command_declines_when_disabled(): void
    {
        $user = $this->staff();

        // حضورٌ قريبٌ عمداً: سقفُ المناوبة (٨ ساعات) آليّةٌ أخرى تعمل
        // دائماً وتُختبر في ملفّها — وحضورُ الثامنة صباحاً يبلغ السقفَ
        // متى شُغّلت الاختباراتُ مساءً فيُقفل بحقٍّ ويكسر هذا الاختبار
        $record = $this->openRecord($user, now()->subHours(2)->format('H:i'));

        $this->artisan('hr:close-attendance')
            ->expectsOutputToContain('بزرّ الانصراف وحده')
            ->assertSuccessful();

        $this->assertNull($record->fresh()->check_out_at);
    }

    /** و--force يتجاوز لمن أراد إقفالاً يدويّاً واعياً — على أثرٍ حقيقيّ. */
    public function test_force_still_closes_for_a_deliberate_manual_run(): void
    {
        $user = $this->staff();
        $record = $this->openRecord($user, now()->subHours(3)->format('H:i'));
        DB::table('users')->where('id', $user->id)->update(['last_seen_at' => now()->subHours(2)]);

        $this->artisan('hr:close-attendance --force')->assertSuccessful();

        $this->assertNotNull($record->fresh()->check_out_at);
    }

    /** وزرُّ الانصراف يعمل كما كان — هو الطريق الوحيد. */
    public function test_the_button_remains_the_one_way_to_check_out(): void
    {
        $user = $this->staff();
        $record = $this->openRecord($user, '08:00');

        AttendanceGuard::checkOut($user);

        $fresh = $record->fresh();
        $this->assertNotNull($fresh->check_out_at);
        $this->assertSame('completed', $fresh->status);
    }

    /**
     * وسجلُّ أمسِ المفتوح لا يُعرض «حاضراً» إلى الأبد.
     *
     * بلا إقفالٍ ليليّ يبقى السجلّ مفتوحاً، وكان `statusOf` يقرأ كلَّ
     * مفتوحٍ «حاضراً» — فيظهر الموظّف في كشف الشهر حاضراً منذ الثلاثاء.
     */
    public function test_yesterdays_open_record_reads_unclosed_not_present(): void
    {
        $user = $this->staff();
        $old = HrAttendance::create([
            'user_id' => $user->id,
            'work_date' => now()->subDay()->toDateString(),
            'check_in_at' => now()->subDay()->setTimeFromTimeString('08:00'),
            'status' => 'present',
            'source' => 'login',
        ]);

        $this->assertSame('unclosed', AttendanceGuard::statusOf($old->fresh()));
        $this->assertSame('present', AttendanceGuard::statusOf($this->openRecord($user)));
    }

    /** ومكتبٌ فضّل الإقفال الليليّ القديم يفعّله فيعود — بأثرٍ حقيقيّ لا بصفر. */
    public function test_an_office_can_opt_back_into_nightly_closing(): void
    {
        Setting::set('hr_auto_close', '1');
        $user = $this->staff();
        $record = $this->openRecord($user, now()->subHours(3)->format('H:i'));
        DB::table('users')->where('id', $user->id)->update(['last_seen_at' => now()->subHours(2)]);

        $this->assertSame(1, AttendanceGuard::closeStaleRecords());
        $this->assertNotNull($record->fresh()->check_out_at);
    }
}
