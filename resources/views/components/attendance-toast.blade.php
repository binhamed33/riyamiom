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
    $flash = session('attendance_flash');
    $open = $attendanceOpen ?? null;
    $dismissed = session('attendance_prompt_dismissed') === \App\Models\HrAttendance::today();
    $showPrompt = $open && ! $flash && ! $dismissed;
@endphp

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
            <p class="font-semibold text-sm text-gray-700">
                {{ $flash['created'] ? 'تم تسجيل حضورك بنجاح' : 'أنت مسجّل حضور اليوم' }}
            </p>
            <p class="text-xs mt-0.5 text-gray-400">
                تم تسجيل حضورك اليوم الساعة <span dir="ltr">{{ $flash['at'] }}</span>
            </p>
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
                تم تسجيل حضورك الساعة
                <span dir="ltr">{{ $open->check_in_at->timezone('Asia/Muscat')->format('h:i A') }}</span>.
            </p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
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
