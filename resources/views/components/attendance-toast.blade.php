{{--
    إشعار الحضور.

    حالتان لا ثالثة:
    ١ — لحظة الدخول: «تم تسجيل حضورك» مع الوقت. يظهر مرّة ثم ينصرف.
    ٢ — العودة وسجلُّه مفتوح: «أنت مسجّل حضور حاليًا» بزرَّين حقيقيين
        يُرسلان إلى الخادم — لا زرَّين يغيّران صنفاً في الصفحة.

    والسؤال لا يتكرّر: من ضغط «استمرار» تُوضع علامةٌ في جلسته فلا
    يُسأل ثانيةً اليوم.
--}}
@php
    // يُسحب لا يُقرأ: كان يُقرأ فيبقى في الجلسة، فيعود إشعارُ الدخول في كلّ
    // صفحةٍ سبعَ ثوانٍ، ويحجب سؤالَ «أما زلت حاضراً؟» طولَ الجلسة.
    //
    // ولا يُسحب في طلبات الآلة: صفحةُ الدخول تُرسل النموذجَ بـfetch وتتبع
    // التحويلَ إلى لوحة القيادة (صفحةٌ لا يراها أحد) ثمّ تنتقل إليها فعلاً —
    // فكان السحبُ الأوّل يلتهم الإشعارَ ولا يظهر «تم تسجيل حضورك» لأحد.
    $flash = request()->ajax() ? session('attendance_flash') : session()->pull('attendance_flash');
    $open = $attendanceOpen ?? null;
    $featureOff = \App\Models\Setting::get('feature_hr', '0') === '1';
    $dismissed = session('attendance_prompt_dismissed') === \App\Models\HrAttendance::today();
    // لا يُسأل حيث بطاقةُ الحضور نفسُها على الشاشة: زرّان للانصراف في صفحةٍ واحدة يُربكان
    // — لوحةُ القيادة وتبويبُ الحضور وحدُه، لا كلُّ تبويبات الموارد البشريّة
    $hasCard = request()->routeIs('dashboard')
        || (request()->routeIs('hr.index') && (request()->get('tab') ?? (in_array(auth()->user()->role, ['admin', 'developer'], true) ? 'employees' : 'attendance')) === 'attendance');
    $showPrompt = $open && ! $flash && ! $dismissed && ! $featureOff && ! $hasCard;
    // «لستُ في دوامي»: دخولٌ ليليٌّ من البيت يفتح يوماً يبلغ السقفَ صباحاً بثماني ساعاتٍ لم تُعمل
    $cancellable = $open && \App\Support\AttendanceGuard::cancellable($open);

    // ═══ انصرافٌ بالزرّ قبل قليل — يُعرض استئنافُه ولا يُفتح خلسةً ═══
    //
    // من ضغط الانصرافَ ظهراً وعاد عصراً كان يُقال له «يومك مكتمل» ولا
    // شيءَ يفتحه؛ والفتحُ التلقائيّ عند الدخول يفتح يومَ من دخل من بيته
    // ليلاً لدقيقتين. فالسؤالُ هو الطريقُ الوسط — ويُغلق مرّةً في اليوم.
    $closedToday = (! $open && ! $flash && \App\Support\AttendanceGuard::tracks(auth()->user()))
        ? \App\Models\HrAttendance::todayFor(auth()->id())
        : null;
    $resumeDismissed = session('attendance_resume_dismissed') === \App\Models\HrAttendance::today();
    $showResume = $closedToday && \App\Support\AttendanceGuard::resumable($closedToday) && ! $resumeDismissed && ! $featureOff;
@endphp

@if($showResume)
    <div class="mb-4 rounded-2xl px-5 py-4 flex flex-col sm:flex-row sm:items-center gap-4"
         style="background: rgba(212,175,55,.10); border: 1px solid rgba(212,175,55,.28);" data-attendance-resume>
        <div class="flex-1 min-w-0">
            {{-- ما كتبه النظامُ لا يُنسب إلى صاحبه: «أُقفل يومُك آليّاً» لا «سجّلت انصرافك» --}}
            @if($closedToday->closedByInference())
                <p class="font-semibold text-sm text-amber-700">أُقفل يومُك آليّاً الساعة <span dir="ltr">{{ $closedToday->check_out_at->timezone('Asia/Muscat')->format('h:i A') }}</span> ({{ $closedToday->inferredLabel() }}).</p>
            @else
                <p class="font-semibold text-sm text-gray-700">سجّلت انصرافك الساعة <span dir="ltr">{{ $closedToday->check_out_at->timezone('Asia/Muscat')->format('h:i A') }}</span>.</p>
            @endif
            <p class="text-xs mt-0.5 text-gray-400">إن كنت ما زلت في دوامك فاستأنفه — ما سبق محفوظٌ وتُضاف الدقائق من الآن.</p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
            <form method="POST" action="{{ route('attendance.keep') }}">
                @csrf
                <input type="hidden" name="dismiss" value="resume">
                <button type="submit" class="px-4 py-2 rounded-xl text-sm font-semibold transition border border-gray-200 text-gray-500 hover:bg-gray-50">انتهى يومي</button>
            </form>
            <form method="POST" action="{{ route('hr.attendance.resume') }}">
                @csrf
                <button type="submit" class="px-4 py-2 rounded-xl text-sm font-semibold transition bg-primary hover:bg-primary-dark text-white">استئناف الدوام</button>
            </form>
        </div>
    </div>
@endif

@if($flash)
    <div x-data="{ show: true }"
         x-show="show"
         x-init="setTimeout(() => show = false, 7000)"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-end="opacity-0 translate-y-2"
         class="mb-4 rounded-2xl px-5 py-4 flex items-center gap-4"
         style="background: rgba(16,185,129,.10); border: 1px solid rgba(16,185,129,.30);">
        <span class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center"
              style="background: rgba(16,185,129,.16);">
            <svg class="w-5 h-5" style="color:#10B981" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
        </span>
        <div class="flex-1 min-w-0">
            @php $resumed = (bool) ($flash['resumed'] ?? false); @endphp
            <p class="font-semibold text-sm text-gray-700">
                {{ $resumed ? 'استُؤنف دوامك — ما سبق محفوظ' : ($flash['created'] ? 'تم تسجيل حضورك بنجاح' : 'أنت مسجّل حضور اليوم') }}
            </p>
            {{-- div لا p: نموذجٌ داخل فقرةٍ يُغلقها المتصفّح فيقفز الزرُّ إلى سطرٍ وحده --}}
            <div class="text-xs mt-0.5 text-gray-400">
                {{ $resumed ? 'بدأت فترةٌ جديدة الساعة' : 'تم تسجيل حضورك اليوم الساعة' }} <span dir="ltr">{{ $flash['at'] }}</span>
                @if($cancellable && $flash['created'])
                    {{-- نموذجٌ لا رابطٌ بمعالجٍ مضمَّن: سياسةُ CSP تمنع onclick --}}
                    · <form method="POST" action="{{ route('hr.attendance.cancel') }}" class="inline">@csrf<button type="submit" data-attendance-cancel class="underline hover:text-gray-600" title="دخولٌ عابر لا دوام — يُلغى تسجيلُ الحضور التلقائيّ ما دام لم تمضِ عليه نصفُ ساعة">لستُ في دوامي</button></form>
                @endif
            </div>
        </div>
        <button @click="show = false" class="p-2 -m-2 flex-shrink-0 opacity-50 hover:opacity-100 transition" aria-label="إغلاق">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
@endif

@if($showPrompt)
    <div class="mb-4 rounded-2xl px-5 py-4 flex flex-col sm:flex-row sm:items-center gap-4"
         style="background: rgba(212,175,55,.10); border: 1px solid rgba(212,175,55,.28);">
        <span class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center"
              style="background: rgba(212,175,55,.16);">
            <span class="w-3 h-3 rounded-full" style="background:#10B981; box-shadow:0 0 0 4px rgba(16,185,129,.2)"></span>
        </span>
        <div class="flex-1 min-w-0">
            <p class="font-semibold text-sm text-gray-700">أنت مسجّل حضور حاليًا.</p>
            <p class="text-xs mt-0.5 text-gray-400">
                {{-- بدايةُ الفترة المفتوحة لا الحضورُ الأوّل: من استأنف عصراً كان يقرأ «حضورك ٨:٠٠» فيظنّ العصرَ غيرَ مسجَّل --}}
                @if($open->resumed_at)
                    استُؤنف دوامك الساعة
                @elseif(! ($open->work_date?->isToday() ?? true))
                    سجلُّ أمسِ ما زال مفتوحاً منذ
                @else
                    تم تسجيل حضورك الساعة
                @endif
                <span dir="ltr">{{ $open->intervalStart()->timezone('Asia/Muscat')->format('h:i A') }}</span>.
            </p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
            @if($cancellable)
                <form method="POST" action="{{ route('hr.attendance.cancel') }}">
                    @csrf
                    <button type="submit" class="px-3 py-2 rounded-xl text-xs font-semibold transition text-gray-500 hover:text-gray-700" title="دخولٌ عابر لا دوام — يُلغى تسجيلُ الحضور التلقائيّ ما دام لم تمضِ عليه نصفُ ساعة">لستُ في دوامي</button>
                </form>
            @endif
            <form method="POST" action="{{ route('attendance.keep') }}">
                @csrf
                <button type="submit"
                        class="px-4 py-2 rounded-xl text-sm font-semibold transition border border-gold-dark text-gold-dark hover:bg-gold/5">
                    استمرار الحضور
                </button>
            </form>
            <form method="POST" action="{{ route('hr.attendance.checkout') }}">
                @csrf
                <button type="submit"
                        class="px-4 py-2 rounded-xl text-sm font-semibold transition bg-primary hover:bg-primary-dark text-white">
                    تسجيل الانصراف
                </button>
            </form>
        </div>
    </div>
@endif
