{{--
    إشعارُ بلوغ حدّ الباقة.

    ═══ لماذا وُجد ═══

    أربعةُ متحكّمات كانت تمنع الإنشاء وتضع في الجلسة limit_reached ومعه
    رسالةٌ مكتوبة تشرح السبب وتذكر الترقية — ولم يكن في التطبيق كلِّه
    موضعٌ واحد يقرؤهما. فكان المستخدم يضغط «حفظ» فلا يُحفظ شيء ولا يُقال
    له لماذا: صفحةٌ تعود كما كانت، ونجاحٌ لم يقع وخطأٌ لم يُعلَن.

    وهو هنا في التخطيط لا في صفحةٍ بعينها: أربعةُ موارد اليوم، وما يُضاف
    غداً يجد الشاشة جاهزة بلا أن يتذكّرها أحد.
--}}
@php
    $limitResource = session('limit_reached');
@endphp

@if ($limitResource)
    @php
        $limitLabel = \App\Support\PlanLimits::RESOURCES[$limitResource] ?? $limitResource;
        $limitValue = \App\Support\PlanLimits::of($limitResource);
        $limitUsed = \App\Support\PlanLimits::used($limitResource);
        $limitPlan = \App\Support\PlanLimits::planName();
        $canRequest = in_array(auth()->user()?->role, ['admin', 'developer'], true);
    @endphp

    <div class="mb-6 rounded-xl p-4"
         style="background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.2);"
         role="alert">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 shrink-0 mt-0.5 text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            </svg>

            <div class="flex-1 min-w-0">
                <p class="font-semibold text-amber-400">
                    باقتك لا تسمح بإضافة {{ $limitLabel }} أكثر
                </p>

                <p class="text-sm text-gray-300 mt-1 leading-relaxed">
                    @if ($limitValue !== null)
                        باقة <span class="font-semibold">{{ $limitPlan ?: 'مكتبك' }}</span>
                        تسمح بـ
                        {{-- dir=ltr: «6 / 5» في فقرةٍ عربية تنقلب فيُقرأ الحدُّ استهلاكاً --}}
                        <span dir="ltr" class="font-semibold">{{ $limitUsed }} / {{ $limitValue }}</span>
                        @if ($limitResource === 'storage_gb') جيجابايت @endif
                        — وقد بلغتَ الحدّ.
                    @else
                        تعذّر إتمام العملية بسبب ازدحامٍ لحظي. أعد المحاولة بعد لحظات.
                    @endif
                </p>

                @if ($limitValue !== null)
                    <p class="text-xs text-gray-400 mt-2">
                        ما هو مسجَّل عندك يبقى كما هو — المنع على الجديد وحده.
                    </p>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        @if ($canRequest)
                            <form method="POST" action="{{ route('plan.upgrade') }}" data-once>
                                @csrf
                                <input type="hidden" name="reason"
                                       value="بلغ المكتب حدّ {{ $limitLabel }} ({{ $limitUsed }}/{{ $limitValue }})">
                                <button type="submit"
                                        class="px-4 py-2 rounded-lg text-sm font-semibold bg-amber-500/15 text-amber-300 border border-amber-500/30 hover:bg-amber-500/25 transition">
                                    اطلب ترقية الباقة
                                </button>
                            </form>
                            <span class="text-xs text-gray-500">يصل طلبك إلى فريق مُداوَلة، ونتواصل معك.</span>
                        @else
                            {{-- لا يُعرض زرٌّ سيُرفض: الترقية تُطلب من إدارة المكتب --}}
                            <span class="text-xs text-gray-400">
                                لترقية الباقة تواصل مع مدير المكتب — الطلب يُرسَل من حسابه.
                            </span>
                        @endif
                    </div>
                @endif

                @error('upgrade')
                    <p class="text-xs text-red-400 mt-2">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>
@endif
