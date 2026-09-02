@extends('layouts.app')

@section('title', __('app.page_settings'))

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-3xl font-bold text-gold-dark">{{ __('app.settings') }}</h1>
    </div>

    @php
        $subService = app(\App\Services\SubscriptionService::class);
        $isDev = auth()->user()->isDeveloper();
        $subInfo = $isDev ? null : $subService->info();
        $subKey = $subInfo['key'] ?? null;
        $subPct = 0;
        if ($subInfo && $subInfo['start_at'] && $subInfo['end_at']) {
            $totalSecs = max(1, (int) $subInfo['start_at']->diffInSeconds($subInfo['end_at']));
            $elapsedSecs = max(0, min($totalSecs, (int) $subInfo['start_at']->diffInSeconds(now())));
            $subPct = (int) round($elapsedSecs / $totalSecs * 100);
        }
    @endphp

    @if($isDev)
        <a href="{{ route('developer.subscription.config') }}" class="block bg-white rounded-xl border border-gray-200 p-5 hover:border-gold/50 transition-colors">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-gold-light to-gold-dark flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <div class="flex-1">
                    <h2 class="text-base font-bold text-gray-800">إعدادات الاشتراك</h2>
                    <p class="text-xs text-gray-500 mt-0.5">إدارة اشتراك هذه النسخة من النظام — متاح للمطور فقط</p>
                </div>
                <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </div>
        </a>
    @elseif($subInfo)
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-gold-light to-gold-dark flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <div class="flex-1">
                    <h2 class="text-base font-bold text-gray-800">اشتراك النظام</h2>
                    <p class="text-xs text-gray-500 mt-0.5">يُدار من قبل المطور</p>
                </div>
                @if(\App\Support\PlanLimits::planName())
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-gold/12 text-gold-dark border border-gold/25">{{ \App\Support\PlanLimits::planName() }}</span>
                @endif
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold {{ \App\Services\SubscriptionService::colorClasses($subInfo['color']) }}">{{ $subInfo['label'] }}</span>
            </div>

            @if($subInfo['start_at'] && $subInfo['end_at'])
                <div class="grid grid-cols-2 gap-4 text-sm mb-4">
                    <div class="bg-gray-50 rounded-lg px-4 py-3">
                        <p class="text-xs text-gray-400 mb-1">تاريخ البدء</p>
                        <p class="font-semibold text-gray-800" dir="ltr">{{ $subInfo['start_at']->format('d/m/Y') }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg px-4 py-3">
                        <p class="text-xs text-gray-400 mb-1">تاريخ الانتهاء</p>
                        <p class="font-semibold text-gray-800" dir="ltr">{{ $subInfo['end_at']->format('d/m/Y') }}</p>
                    </div>
                </div>

                @if(in_array($subKey, ['active', 'expiring_soon']))
                    <div>
                        <div class="flex justify-between text-xs text-gray-500 mb-1.5">
                            <span>مدة الاشتراك</span>
                            <span class="font-semibold {{ $subKey === 'expiring_soon' ? 'text-amber-600' : 'text-emerald-600' }}">
                                {{ $subPct }}% — متبقي {{ $subInfo['remaining_days'] }} يوم
                            </span>
                        </div>
                        <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full {{ $subKey === 'expiring_soon' ? 'bg-amber-500' : 'bg-emerald-500' }}" style="width: {{ $subPct }}%"></div>
                        </div>
                    </div>
                @endif
            @else
                <div class="bg-gray-50 rounded-lg px-4 py-3 text-sm text-gray-500">
                    لا يوجد اشتراك مفعّل لهذا النظام حاليًا. يرجى التواصل مع المطور لتفعيل الوصول الكامل.
                </div>
            @endif

            {{-- سعةُ الباقة: «كم بقي لي» بعين المكتب لا بسؤال أحد.
                 الحدودُ تنزل من اللوحة مع النبضة، والاستهلاكُ يُحسب من
                 قاعدة المكتب نفسِه — فالرقمان صادقان معاً. --}}
            @php $capLimits = \App\Support\PlanLimits::all(); @endphp
            @if($capLimits !== [])
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <p class="text-xs font-bold text-gray-500 mb-3">سعة الباقة</p>
                    <div class="grid sm:grid-cols-2 gap-x-6 gap-y-3">
                        @foreach(\App\Support\PlanLimits::RESOURCES as $capKey => $capLabel)
                            @php
                                $capLimit = $capLimits[$capKey] ?? null;
                                $capUsed = \App\Support\PlanLimits::used($capKey);
                                $capPct = $capLimit ? min(100, (int) round($capUsed / max(1, $capLimit) * 100)) : 0;
                                $capUnit = $capKey === 'storage_gb' ? ' GB' : '';
                            @endphp
                            <div>
                                <div class="flex justify-between text-[11px] text-gray-500 mb-1">
                                    <span>{{ $capLabel }}</span>
                                    <span class="font-semibold {{ $capPct >= 90 ? 'text-red-600' : ($capPct >= 70 ? 'text-amber-600' : 'text-gray-600') }}" dir="ltr">
                                        {{ number_format($capUsed) }}{{ $capUnit }} / {{ $capLimit ? number_format($capLimit) . $capUnit : '∞' }}
                                    </span>
                                </div>
                                <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full {{ $capPct >= 90 ? 'bg-red-500' : ($capPct >= 70 ? 'bg-amber-500' : 'bg-gradient-to-l from-gold-light to-gold-dark') }}" style="width: {{ max(2, $capPct) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- ===== هوية المكتب: شعار خاص بهذا المكتب وحده ===== --}}
    @php
        $officeLogoUrl = \App\Support\OfficeBrand::logoUrl();
        $officeBrandName = \App\Support\OfficeBrand::name();
    @endphp
    <div class="bg-white rounded-xl border border-gray-200 p-5" x-data="{ picked: null }">
        <div class="flex items-center gap-3 mb-1">
            <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-gold-light to-gold-dark flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
            <div class="flex-1">
                <h2 class="text-base font-bold text-gray-800">هوية المكتب</h2>
                <p class="text-xs text-gray-500 mt-0.5">شعار مكتبك يظهر في القائمة الجانبية وشاشة الدخول والمستندات المطبوعة — وهو خاص بمكتبك وحده.</p>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row items-start gap-5 mt-5">
            {{-- المعاينة --}}
            <div class="flex flex-col items-center gap-2 shrink-0">
                <div class="w-28 h-28 rounded-2xl border border-gray-200 bg-gray-50 overflow-hidden flex items-center justify-center">
                    <template x-if="picked">
                        <img :src="picked" alt="معاينة الشعار" class="w-full h-full object-contain">
                    </template>
                    <template x-if="!picked">
                        <div class="w-full h-full flex items-center justify-center">
                            @if($officeLogoUrl)
                                <img src="{{ $officeLogoUrl }}" alt="{{ $officeBrandName }}" class="w-full h-full object-contain">
                            @else
                                <span class="text-3xl font-heading font-bold text-gold-dark">م</span>
                            @endif
                        </div>
                    </template>
                </div>
                <span class="text-[11px] text-gray-400">{{ $officeLogoUrl ? 'الشعار الحالي' : 'لا يوجد شعار' }}</span>
            </div>

            {{-- الرفع --}}
            <div class="flex-1 w-full">
                <form method="POST" action="{{ route('settings.logo.update') }}" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <label class="block">
                        <span class="block text-sm font-medium text-gray-700 mb-2">اختر ملف الشعار</span>
                        <input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp,.svg" required
                               x-on:change="picked = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null"
                               class="block w-full text-sm text-gray-600 file:mr-4 file:rtl:ml-4 file:rtl:mr-0 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-bold file:bg-gold/12 file:text-gold-dark hover:file:bg-gold/20 cursor-pointer">
                    </label>
                    <p class="text-[11px] text-gray-400">PNG أو JPG أو WEBP أو SVG — بحد أقصى ١ ميجابايت. يُفضّل شعار مربّع بخلفية شفافة.</p>

                    @error('logo')
                        <p class="text-xs text-red-600 font-semibold">{{ $message }}</p>
                    @enderror

                    <div class="flex flex-wrap items-center gap-2 pt-1">
                        <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-5 py-2.5 rounded-lg font-semibold transition-colors text-sm">
                            حفظ الشعار
                        </button>
                        <button type="button" x-show="picked" x-cloak x-on:click="picked = null; $el.closest('form').reset()"
                                class="text-sm text-gray-500 hover:text-gray-800 px-3 py-2.5">إلغاء الاختيار</button>
                    </div>
                </form>

                @if($officeLogoUrl)
                    <form method="POST" action="{{ route('settings.logo.destroy') }}" class="mt-3"
                          data-confirm="حذف شعار المكتب؟ سيعود النظام لهوية مُداوَلة الافتراضية.">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-xs font-bold text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 px-3 py-1.5 rounded-lg transition">
                            حذف الشعار
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <div class="mt-5 pt-4 border-t border-gray-100 flex items-start gap-2 text-[11px] text-gray-500">
            <span class="text-gold-dark">ⓘ</span>
            <span>هوية المنتج <b class="text-gold-dark">مُداوَلة</b> تبقى كما هي في النظام؛ شعارك يمثّل مكتبك أنت. ولا يمكن لأي مكتب آخر الوصول إلى شعارك.</span>
        </div>
    </div>


    {{-- ===== واتساب الأعمال: رقم هذا المكتب وحده ===== --}}
    @php
        $wa = \App\Support\WhatsAppSettings::snapshot();
        $waVerify = \App\Support\WhatsAppSettings::verifyToken();
        $waEvolution = \App\Support\WhatsAppSettings::usingEvolution();
        $waState = $waEvolution ? \App\Support\WhatsAppSettings::evolutionState() : null;
        $waTemplates = \Illuminate\Support\Facades\Schema::hasTable('whatsapp_templates')
            ? \App\Models\WhatsAppTemplate::where('status', 'APPROVED')->orderBy('name')->get()
            : collect();
    @endphp
    <div class="p-6 rounded-2xl glass-card" id="whatsapp-settings">
        <div class="flex items-center justify-between gap-3 flex-wrap border-b border-gray-200 pb-3 mb-4">
            <div class="flex items-center gap-2.5">
                <h2 class="text-base font-bold text-gray-800">{{ __('app.wa_settings_title') }}</h2>
                @if($wa['needs_attention'])
                    <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200">{{ __('app.wa_needs_attention') }}</span>
                @elseif($wa['connected'])
                    <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">● {{ __('app.wa_connected') }}</span>
                @else
                    <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 border border-gray-200">{{ __('app.wa_disconnected') }}</span>
                @endif
            </div>
            @if($wa['connected'])
                <button type="button" data-wa-test class="text-xs font-bold px-3 py-1.5 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50">{{ __('app.wa_test') }}</button>
            @endif
        </div>

        <p class="text-xs text-gray-500 leading-relaxed mb-4">{{ __($waEvolution ? 'app.wa_connect_help_evolution' : 'app.wa_connect_help') }}</p>

        @if($waEvolution)
        {{-- ═══ اقترانٌ بمسح رمز — جسر واتساب ويب ═══

             لا رمزَ يُنسخ ولا لوحةَ Meta: يُمسح الرمزُ من واتساب في
             الهاتف كما يُربط واتساب ويب. الشاشةُ تسأل الحالةَ كلَّ
             ثانيتين حتى تصير «open». --}}
        <div class="mb-5 rounded-xl border border-gray-200 overflow-hidden" data-wa-pair
             data-pair-url="{{ route('settings.whatsapp.pair') }}"
             data-state-url="{{ route('settings.whatsapp.pair-state') }}">
            <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-200 flex items-center justify-between gap-3">
                <span class="text-xs font-bold text-gray-700">اقتران الرقم</span>
                <span class="text-[11px] px-2 py-0.5 rounded-full border
                             {{ $waState === 'open' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-gray-100 text-gray-500 border-gray-200' }}"
                      data-pair-badge>{{ $waState === 'open' ? '● موصول' : 'غير موصول' }}</span>
            </div>
            <div class="p-4 grid sm:grid-cols-[auto_1fr] gap-5 items-center">
                <div class="w-48 h-48 mx-auto rounded-xl border border-gray-200 bg-white flex items-center justify-center overflow-hidden"
                     data-pair-qr-box>
                    <span class="text-xs text-gray-400 text-center px-4" data-pair-placeholder>
                        {{ $waState === 'open' ? 'الرقم موصول — لا حاجة للمسح.' : 'اضغط «ابدأ الاقتران» ليظهر الرمز.' }}
                    </span>
                </div>
                <div class="text-xs text-gray-600 leading-relaxed space-y-2">
                    <p class="font-bold text-gray-800">كيف تربط رقمك:</p>
                    <ol class="space-y-1.5 list-decimal pr-4">
                        <li>افتح <span class="font-semibold">واتساب</span> في الهاتف الذي يحمل رقم المكتب.</li>
                        <li>الإعدادات ← <span class="font-semibold">الأجهزة المرتبطة</span> ← ربط جهاز.</li>
                        <li>وجّه الكاميرا إلى الرمز الظاهر هنا.</li>
                    </ol>
                    <p class="text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-2 mt-2" data-pair-status>
                        الرقمُ الذي تمسحه يصبح رقمَ المكتب في النظام. جرّب برقمٍ جانبيّ أوّلاً.
                    </p>
                    <button type="button" data-pair-start
                            class="mt-1 text-xs font-bold px-4 py-2 rounded-lg bg-gold text-white hover:bg-gold-dark">
                        {{ $waState === 'open' ? 'إعادة الاقتران' : 'ابدأ الاقتران' }}
                    </button>

                    {{-- ═══ البابُ الثاني: الربطُ بالرقم ═══

                         واتساب يرفض ربطَ أجهزةٍ جديدةٍ أحياناً بعد
                         محاولاتٍ متكرّرة («Can't link new devices right
                         now») فيقف المكتبُ أمام رمزٍ صحيحٍ لا يُقبل.
                         والربطُ بالرقم مسارٌ آخر عند واتساب نفسِه —
                         وهو الخيارُ المعروض في شاشة المسح في الهاتف. --}}
                    <div class="mt-3 pt-3 border-t border-gray-200">
                        <button type="button" data-pair-phone-toggle
                                class="text-[11px] text-gray-500 hover:text-gold-dark underline">
                            تعذّر المسح؟ اربط برقم الهاتف بدلاً من ذلك
                        </button>

                        <div class="hidden mt-2 space-y-2" data-pair-phone-box>
                            <label class="block text-[11px] text-gray-600" for="wa-pair-phone">
                                رقم المكتب بصيغته الدولية بلا صفرٍ ولا زائد
                            </label>
                            <div class="flex gap-2">
                                <input id="wa-pair-phone" type="tel" inputmode="numeric" dir="ltr" maxlength="20"
                                       placeholder="96891234567" data-pair-phone
                                       class="flex-1 rounded-lg border border-gray-200 px-3 py-2 text-xs text-gray-900">
                                <button type="button" data-pair-phone-start
                                        class="text-xs font-bold px-4 py-2 rounded-lg bg-gray-800 text-white hover:bg-black whitespace-nowrap">
                                    اطلب رمزاً
                                </button>
                            </div>
                            <p class="text-[11px] text-gray-500 leading-relaxed">
                                في الهاتف: واتساب ← الأجهزة المرتبطة ← ربط جهاز ←
                                <span class="font-semibold">«الربط برقم الهاتف بدلاً من ذلك»</span> ← اكتب الرمز الظاهر هنا.
                            </p>
                            <div class="hidden text-center font-mono tracking-[0.35em] text-lg font-bold text-gray-900
                                        bg-gray-50 border border-gray-200 rounded-lg py-3" data-pair-code></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @else
        {{-- ═══ معالجُ الربط ═══

             الربطُ خمسُ خطوات، ثلاثٌ منها في لوحة Meta لا هنا. وحين لا
             تصل رسالة لا يعرف صاحبُ المكتب أيَّ خطوةٍ سقطت — فيعيدها
             كلَّها مراراً، وكلُّها تبدو صحيحةً عند Meta.

             فتُعرض الخطواتُ بترتيبها، وواحدةٌ فقط هي «التالية»،
             وسببُ تعثّرها مكتوبٌ تحتها. والحالةُ المخزَّنة تُعرض عند
             فتح الصفحة، و«افحص الآن» يسأل Meta فعلاً. --}}
        @php $waSetup = app(\App\Services\WhatsApp\SetupDoctor::class)->report(); @endphp
        <div class="mb-5 rounded-xl border border-gray-200 overflow-hidden" data-wa-wizard>
            <div class="flex items-center justify-between gap-3 px-4 py-2.5 bg-gray-50 border-b border-gray-200">
                <span class="text-xs font-bold text-gray-700">خطوات الربط</span>
                <div class="flex items-center gap-2">
                    <span class="text-[11px] text-gray-400" data-wa-wizard-note>الحالة المحفوظة — لم يُسأل Meta بعد</span>
                    <button type="button" data-wa-checkup
                            class="text-xs font-bold px-3 py-1.5 rounded-lg bg-gold/10 border border-gold/40 text-gold-dark hover:bg-gold/20">
                        افحص الآن
                    </button>
                    {{-- لا يُعرض إلا حين يمكن فعلاً: زرٌّ يظهر ثمّ يقول
                         «ينقصني كذا» أسوأ من زرٍّ لا يظهر. --}}
                    @if($wa['can_autowire'])
                        <button type="button" data-wa-autowire="1"
                                class="text-xs font-bold px-3 py-1.5 rounded-lg bg-gold text-white hover:bg-gold-dark">
                            أكمل الربط تلقائياً
                        </button>
                    @endif
                </div>
            </div>
            <ol class="divide-y divide-gray-100" data-wa-steps>
                @foreach($waSetup['steps'] as $i => $step)
                    <li class="flex items-start gap-3 px-4 py-3" data-wa-step="{{ $step['key'] }}">
                        <span class="mt-0.5 w-5 h-5 shrink-0 rounded-full text-[10px] font-bold flex items-center justify-center
                                     {{ $step['state'] === 'done' ? 'bg-emerald-100 text-emerald-700' : ($step['state'] === 'next' ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-400') }}"
                              data-wa-step-badge>{{ $step['state'] === 'done' ? '✓' : $i + 1 }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-baseline gap-2 flex-wrap">
                                <span class="text-sm font-semibold text-gray-800" data-wa-step-title>{{ $step['title'] }}</span>
                                <span class="text-[11px] text-gray-400" dir="ltr" data-wa-step-where>{{ $step['where'] }}</span>
                                @if(!$step['required'])
                                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-500 border border-gray-200">اختياري</span>
                                @endif
                            </div>
                            <p class="text-xs text-gray-500 mt-0.5 leading-relaxed" data-wa-step-reason>{{ $step['reason'] }}</p>
                            <p class="text-xs text-gold-dark mt-1 leading-relaxed {{ $step['action'] ? '' : 'hidden' }}" data-wa-step-action>{{ $step['action'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
            <div class="hidden px-4 py-3 border-t border-gray-100 text-xs leading-relaxed" data-wa-autowire-result></div>
        </div>
        @endif

        {{-- العنوان ورمز التحقّق: يُلصقان في إعداد الويبهوك عند Meta.
             ليسا سرّاً بالمعنى الذي يُخفى — لكنّهما لا يُنسخان إلا هنا.
             ولا معنى لهما على الجسر: ويبهوكُه يُضبط تلقائياً عند الاقتران. --}}
        @unless($waEvolution)
        <div class="grid md:grid-cols-2 gap-3 mb-5">
            <div>
                <label class="text-xs font-bold text-gray-500">{{ __('app.wa_webhook_url') }}</label>
                <div class="flex items-center gap-2 mt-1">
                    <input type="text" readonly dir="ltr" value="{{ $wa['webhook_url'] }}"
                           class="flex-1 text-xs font-mono px-3 py-2 rounded-lg bg-gray-50 border border-gray-200 text-gray-600">
                    <button type="button" data-wa-copy="{{ $wa['webhook_url'] }}"
                            class="text-xs font-bold px-2.5 py-2 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50">{{ __('app.wa_copy') }}</button>
                </div>
            </div>
            <div>
                <label class="text-xs font-bold text-gray-500">{{ __('app.wa_verify_token') }}</label>
                <div class="flex items-center gap-2 mt-1">
                    <input type="text" readonly dir="ltr" value="{{ $waVerify }}"
                           class="flex-1 text-xs font-mono px-3 py-2 rounded-lg bg-gray-50 border border-gray-200 text-gray-600">
                    <button type="button" data-wa-copy="{{ $waVerify }}"
                            class="text-xs font-bold px-2.5 py-2 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50">{{ __('app.wa_copy') }}</button>
                </div>
            </div>
        </div>
        @endunless

        @if($wa['connected'])
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-5 text-xs">
                <div class="p-3 rounded-xl bg-gray-50 border border-gray-100">
                    <div class="text-gray-400 mb-0.5">{{ __('app.wa_number') }}</div>
                    <div class="font-bold text-gray-700" dir="ltr">{{ $wa['display_phone'] ?: '—' }}</div>
                </div>
                <div class="p-3 rounded-xl bg-gray-50 border border-gray-100">
                    <div class="text-gray-400 mb-0.5">{{ __('app.wa_business_name') }}</div>
                    <div class="font-bold text-gray-700">{{ $wa['business_name'] ?: '—' }}</div>
                </div>
                <div class="p-3 rounded-xl bg-gray-50 border border-gray-100">
                    <div class="text-gray-400 mb-0.5">{{ __('app.wa_phone_id') }}</div>
                    <div class="font-bold text-gray-700 font-mono" dir="ltr">{{ $wa['phone_number_id'] ?: '—' }}</div>
                </div>
                <div class="p-3 rounded-xl bg-gray-50 border border-gray-100">
                    <div class="text-gray-400 mb-0.5">{{ __('app.wa_last_webhook') }}</div>
                    <div class="font-bold text-gray-700">
                        {{ $wa['last_webhook_at'] ? \Illuminate\Support\Carbon::parse($wa['last_webhook_at'])->diffForHumans() : '—' }}
                    </div>
                </div>
            </div>

            @if($wa['error'])
                <div class="mb-5 p-3 rounded-xl bg-red-50 border border-red-200 text-xs text-red-700">{{ $wa['error'] }}</div>
            @endif
        @endif

        <form method="POST" action="{{ route('settings.whatsapp.update') }}" class="space-y-4">
            @csrf
            @unless($waEvolution)
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="text-xs font-bold text-gray-500">{{ __('app.wa_token') }}</label>
                    {{-- الحقل فارغٌ دائماً ولو كان الرمز مضبوطاً: عرضُه ولو
                         مقنَّعاً في قيمة input يضعه في مصدر الصفحة. --}}
                    <input type="password" name="wa_access_token" autocomplete="new-password" dir="ltr"
                           placeholder="{{ $wa['token_hint'] ? $wa['token_hint'] : 'EAAG…' }}"
                           class="w-full mt-1 px-3 py-2 rounded-lg border border-gray-200 text-sm font-mono">
                </div>
                <div>
                    <label class="text-xs font-bold text-gray-500">{{ __('app.wa_app_secret') }}</label>
                    <input type="password" name="wa_app_secret" autocomplete="new-password" dir="ltr"
                           placeholder="••••••••"
                           class="w-full mt-1 px-3 py-2 rounded-lg border border-gray-200 text-sm font-mono">
                </div>
                <div>
                    <label class="text-xs font-bold text-gray-500">{{ __('app.wa_app_id') }}</label>
                    <input type="text" name="wa_app_id" dir="ltr" inputmode="numeric"
                           value="{{ old('wa_app_id') }}"
                           placeholder="{{ \App\Support\WhatsAppSettings::appId() ?: '1234567890' }}"
                           class="w-full mt-1 px-3 py-2 rounded-lg border border-gray-200 text-sm font-mono">
                    <p class="text-[11px] text-gray-400 mt-1">في أعلى صفحة Meta بجانب اسم التطبيق — وبه يُتمّ النظامُ الربطَ عنك.</p>
                </div>
                <div>
                    <label class="text-xs font-bold text-gray-500">{{ __('app.wa_phone_id') }}</label>
                    <input type="text" name="wa_phone_number_id" dir="ltr" inputmode="numeric"
                           value="{{ old('wa_phone_number_id') }}"
                           class="w-full mt-1 px-3 py-2 rounded-lg border border-gray-200 text-sm font-mono">
                    <p class="text-[11px] text-gray-400 mt-1">اتركه فارغاً — يُستنتج من الرمز.</p>
                </div>
                <div>
                    <label class="text-xs font-bold text-gray-500">{{ __('app.wa_waba_id') }}</label>
                    <input type="text" name="wa_business_account_id" dir="ltr" inputmode="numeric"
                           value="{{ old('wa_business_account_id') }}"
                           class="w-full mt-1 px-3 py-2 rounded-lg border border-gray-200 text-sm font-mono">
                    <p class="text-[11px] text-gray-400 mt-1">اتركه فارغاً — يُستنتج من الرمز.</p>
                </div>
            </div>
            @endunless

            {{-- ═══ حدودُ الأمان ═══

                 ما يُحظَر لأجله رقمٌ ليس «استعمالَ أداة» أوّلاً بل
                 السلوك: دفعةٌ في دقيقة، وإرسالٌ إلى غير الموكّلين،
                 ورسائلُ في الثالثة فجراً — ثمّ بلاغات. والبلاغُ هو
                 الوقودُ الحقيقي. --}}
            <div class="pt-3 border-t border-gray-100">
                <div class="flex items-baseline justify-between gap-3 flex-wrap mb-2">
                    <h3 class="text-sm font-bold text-gray-800">حدود الأمان</h3>
                    <span class="text-[11px] text-gray-400">
                        بقي اليوم: {{ \App\Services\WhatsApp\SendingGuard::remainingToday() }} رسالة
                    </span>
                </div>

                @php $waLocked = \App\Services\WhatsApp\SendingGuard::lockedForOffice(); @endphp

                {{-- مقفلةٌ على المكتب ومقروءةٌ له: يرى حالتَها ويعرف
                     لماذا هي كذلك، ولا تُخفى عنه. والحمايةُ في الخادم
                     لا في `disabled` — الحقلُ المعطَّل لا يُرسَل، لكنّ
                     من يبني الطلبَ بيده يرسله. --}}
                <div class="grid sm:grid-cols-2 gap-2 mb-3">
                    <label class="flex items-start gap-2.5 p-2.5 rounded-lg border {{ $waLocked ? 'border-gray-200 bg-gray-50 cursor-not-allowed' : 'border-gray-200 cursor-pointer hover:border-gold/40' }}">
                        <input type="checkbox" name="wa_guard_enabled" value="1" class="mt-0.5 rounded border-gray-300"
                               @checked(\App\Services\WhatsApp\SendingGuard::enabled()) @disabled($waLocked)>
                        <span>
                            <span class="block text-xs font-semibold text-gray-800">
                                تفعيل حدود الأمان
                                @if($waLocked)<span class="text-[10px] font-normal text-gray-400">— يضبطه المطوّر</span>@endif
                            </span>
                            <span class="block text-[11px] text-gray-500">إيقاعٌ متفاوت، وسقوف، وصمتٌ ليلي</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-2.5 p-2.5 rounded-lg border {{ $waLocked ? 'border-gray-200 bg-gray-50 cursor-not-allowed' : 'border-gray-200 cursor-pointer hover:border-gold/40' }}">
                        <input type="checkbox" name="wa_guard_clients_only" value="1" class="mt-0.5 rounded border-gray-300"
                               @checked(\App\Services\WhatsApp\SendingGuard::clientsOnly()) @disabled($waLocked)>
                        <span>
                            <span class="block text-xs font-semibold text-gray-800">
                                الموكّلون فقط
                                @if($waLocked)<span class="text-[10px] font-normal text-gray-400">— يضبطه المطوّر</span>@endif
                            </span>
                            <span class="block text-[11px] text-gray-500">لا يُراسَل رقمٌ ليس في السجلّ ولم يراسل المكتب</span>
                        </span>
                    </label>
                </div>

                @php $waNumCls = 'w-full mt-1 px-2 py-1.5 rounded-lg border border-gray-200 text-sm'
                        . ($waLocked ? ' bg-gray-50 text-gray-500 cursor-not-allowed' : ''); @endphp
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div>
                        <label class="text-[11px] font-bold text-gray-500">في الساعة</label>
                        {{-- المضبوطُ لا المتدرّج: perHour() تعيد قيمةَ
                             التدرّج، وحفظُها هنا كان يكتبها مكان
                             المضبوطة فيتقلّص السقف مع كل حفظة --}}
                        <input type="number" name="wa_guard_per_hour" min="1" max="200"
                               value="{{ \App\Services\WhatsApp\SendingGuard::configuredPerHour() }}"
                               class="{{ $waNumCls }}" @disabled($waLocked)>
                    </div>
                    <div>
                        <label class="text-[11px] font-bold text-gray-500">في اليوم</label>
                        <input type="number" name="wa_guard_per_day" min="1" max="1000"
                               value="{{ \App\Services\WhatsApp\SendingGuard::configuredPerDay() }}"
                               class="{{ $waNumCls }}" @disabled($waLocked)>
                    </div>
                    <div>
                        <label class="text-[11px] font-bold text-gray-500">مهلة بين رسالتين (ث)</label>
                        <input type="number" name="wa_guard_min_gap_s" min="3" max="600"
                               value="{{ \App\Services\WhatsApp\SendingGuard::minGap() }}"
                               class="{{ $waNumCls }}" @disabled($waLocked)>
                    </div>
                    <div>
                        <label class="text-[11px] font-bold text-gray-500">صمتٌ من — إلى</label>
                        <div class="flex items-center gap-1 mt-1">
                            <input type="number" name="wa_guard_quiet_from" min="0" max="23"
                                   value="{{ (int) (\App\Models\Setting::get(\App\Services\WhatsApp\SendingGuard::KEY_QUIET_FROM, 21)) }}"
                                   class="{{ str_replace('mt-1', '', $waNumCls) }}" @disabled($waLocked)>
                            <input type="number" name="wa_guard_quiet_to" min="0" max="23"
                                   value="{{ (int) (\App\Models\Setting::get(\App\Services\WhatsApp\SendingGuard::KEY_QUIET_TO, 8)) }}"
                                   class="{{ str_replace('mt-1', '', $waNumCls) }}" @disabled($waLocked)>
                        </div>
                    </div>
                </div>

                @if(\App\Services\WhatsApp\SendingGuard::warmingUp())
                    <p class="text-[11px] text-amber-600 mt-1.5">
                        الرقمُ في تدرّج ما بعد الاقتران: النافذُ اليوم
                        {{ \App\Services\WhatsApp\SendingGuard::perDay() }} في اليوم
                        و{{ \App\Services\WhatsApp\SendingGuard::perHour() }} في الساعة،
                        ويرتفع يومياً حتى يبلغ المضبوطَ أعلاه.
                    </p>
                @endif
                @if($waLocked)
                    <p class="text-[11px] text-gray-400 mt-1.5">هذه الحدود يضبطها المطوّر — تُعرض لك ولا تُعدَّل من هنا.</p>
                @endif

                <label class="flex items-start gap-2.5 p-2.5 mt-3 rounded-lg border {{ $waLocked ? 'border-gray-200 bg-gray-50 cursor-not-allowed' : 'border-gray-200 cursor-pointer hover:border-gold/40' }}">
                    <input type="checkbox" name="wa_inbox_visible" value="1" class="mt-0.5 rounded border-gray-300"
                           @checked(\App\Support\WhatsAppSettings::inboxVisible()) @disabled($waLocked)>
                    <span>
                        <span class="block text-xs font-semibold text-gray-800">
                            إظهار صندوق وارد واتساب
                            @if($waLocked)<span class="text-[10px] font-normal text-gray-400">— يضبطه المطوّر</span>@endif
                        </span>
                        <span class="block text-[11px] text-gray-500 leading-relaxed">
                            مخفيٌّ افتراضاً. وهو الطريق الوحيد لإرسالٍ يدويٍّ حرّ — وأخطرُ ما على سلامة الرقم.
                            والإشعاراتُ الآلية تعمل بدونه.
                        </span>
                    </span>
                </label>

                {{-- ═══ لا يُقال للمكتب ما لا يحتاج أن يعرفه ═══

                     كان هنا نصٌّ يشرح احتمالَ الحظر ويشير إلى «الواجهة
                     الرسمية» — وهو يكشف لمديرِ مكتبٍ لا يعنيه التفصيلُ
                     التقني أنّ في الطريق ما يقلق، فيُفسَّر على غير وجهه
                     ويُنقل عنه.

                     والحدودُ تعمل كما هي سواءٌ قُرئ الشرحُ أو لم يُقرأ،
                     وهي مقفلةٌ عليه أصلاً. فيبقى ما ينفعه: أنّ الإيقاع
                     منظَّمٌ حفاظاً على رقمه. --}}
                <p class="text-[11px] text-gray-500 leading-relaxed mt-2 bg-gray-50 border border-gray-200 rounded-lg p-2.5">
                    إيقاعُ الإرسال منظَّمٌ تلقائياً حفاظاً على سمعة رقم المكتب:
                    مهلةٌ بين الرسائل، وسقوفٌ يومية، وتوقّفٌ في ساعات الليل.
                    ولا تُلغى رسالة — ما يتجاوز الحدَّ ينتظر دورَه ويُرسَل.
                </p>
            </div>

            {{-- ═══ إشعارات الموكّل ═══

                 واتساب هنا قناةُ تنبيهٍ لا مخزنُ بيانات: الرسالةُ تقول
                 «جدَّ شيء» وتحمل رابطاً آمناً، والتفاصيلُ في البوابة
                 خلف الدخول. ولا يُفتح نوعٌ لم يطلبه المكتب: كلُّ رسالةٍ
                 تُحاسَب، وكلُّ رسالةٍ غير مرغوبة بلاغٌ محتمَل يُنزل
                 تقييمَ الرقم. --}}
            <div class="pt-3 border-t border-gray-100">
                <div class="flex items-baseline justify-between gap-3 flex-wrap mb-2">
                    <h3 class="text-sm font-bold text-gray-800">إشعارات الموكّل</h3>
                    <span class="text-[11px] text-gray-400">تنبيهٌ قصير + رابطٌ آمن للبوابة — بلا تفاصيل في الرسالة</span>
                </div>

                @php $cnLocked = \App\Support\ClientEvents::lockedForOffice(); @endphp

                {{-- المفتاحُ الرئيسي: لا يُراسَل موكّلٌ واحد قبل تشغيله.
                     وحالتُه ظاهرةٌ كما هي — لا خاناتٌ مؤشَّرة على ميزةٍ
                     لا تعمل. --}}
                @php $cnMaster = \App\Support\ClientEvents::masterEnabled(); @endphp

                <label class="flex items-start gap-2.5 p-3 rounded-lg border-2 mb-3 {{ $cnLocked ? 'cursor-default' : 'cursor-pointer' }}
                              {{ $cnMaster ? 'border-gold/50 bg-gold/5' : 'border-gray-200' }}">
                    @if($cnLocked)
                        @include('settings.partials.state-mark', ['on' => $cnMaster])
                    @else
                        <input type="checkbox" name="cn_enabled" value="1" class="mt-0.5 rounded border-gray-300"
                               @checked($cnMaster)>
                    @endif
                    <span class="min-w-0">
                        <span class="block text-sm font-bold text-gray-800">تشغيل إشعارات الموكّل
                            @if($cnLocked)<span class="text-[10px] font-normal text-gray-400">— يضبطها المطوّر</span>@endif
                        </span>
                        <span class="block text-[11px] text-gray-500 leading-relaxed">
                            مشغّلةٌ لكل مكتبٍ تلقائياً — وإطفاؤها بيد المطوّر وحده. المكتبُ غيرُ المربوط برقمٍ يُقيَّد إشعارُه في البوابة فقط.
                        </span>
                    </span>
                </label>

                {{-- علامةُ حضور القسم.

                     نموذجٌ لا يحمل هذه الخانة لا يُبدّل اختياراتِ
                     الأنواع: بلا هذه العلامة كان أيُّ حفظٍ من نموذجٍ
                     لا يعرض هذا القسم يقرأ «لم يُختر شيء» فيكتب
                     صفراً على العشرة — وهو ما أطفأ حدودَ الأمان من
                     قبل بنفس الطريقة. --}}
                <input type="hidden" name="cn_section" value="1">

                <div class="grid sm:grid-cols-2 gap-2 {{ \App\Support\ClientEvents::masterEnabled() ? '' : 'opacity-50' }}">
                    @foreach(\App\Support\ClientEvents::catalogue() as $evtKey => $evt)
                        @php $evtOn = \App\Support\ClientEvents::chosen($evtKey); @endphp

                        {{-- اللونُ يتبع الحالةَ لا القفل.

                             كان المقفولُ كلُّه رمادياً، فبدا المشغَّلُ
                             مطفأً: «لونهم يوحي أنّهم غير فعّالين».
                             والقفلُ يُقال بجملةٍ واحدة تحت القسم، أمّا
                             البطاقةُ فتقول شيئاً واحداً: أمشغّلٌ هذا
                             النوع أم لا. --}}
                        <label class="flex items-start gap-2.5 p-2.5 rounded-lg border
                                      {{ $evtOn ? 'border-gold/50 bg-gold/5' : 'border-gray-200' }}
                                      {{ $cnLocked ? 'cursor-default' : 'cursor-pointer hover:border-gold/40' }}">
                            @if($cnLocked)
                                @include('settings.partials.state-mark', ['on' => $evtOn])
                            @else
                                <input type="checkbox" name="cn_evt[]" value="{{ $evtKey }}" class="mt-0.5 rounded border-gray-300"
                                       @checked($evtOn)>
                            @endif
                            <span class="min-w-0">
                                <span class="block text-xs font-semibold text-gray-800">{{ $evt['label'] }}</span>
                                <span class="block text-[11px] text-gray-500 leading-relaxed">{{ $evt['hint'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>

                <div class="mt-3 grid sm:grid-cols-2 gap-3 items-end">
                    <label class="flex items-center gap-2 text-xs text-gray-700 {{ $cnLocked ? 'cursor-default' : '' }}">
                        @if($cnLocked)
                            @include('settings.partials.state-mark', ['on' => \App\Services\ClientPortal\PortalLinks::enabled(), 'top' => false])
                        @else
                            <input type="checkbox" name="cn_links_enabled" value="1" class="rounded border-gray-300"
                                   @checked(\App\Services\ClientPortal\PortalLinks::enabled())>
                        @endif
                        رابط دخولٍ مباشر في الرسالة
                    </label>
                    <div>
                        <label class="text-xs font-bold text-gray-500">صلاحية الرابط (ساعة)</label>
                        @if($cnLocked)
                            {{-- رقمٌ يُقرأ لا حقلٌ معطَّل: الحقلُ الرمادي
                                 يقول «لا قيمة»، والرقمُ يقول قيمتَه. --}}
                            <p class="mt-1 px-3 py-2 text-sm font-semibold text-gray-800">
                                {{ \App\Services\ClientPortal\PortalLinks::ttlHours() }}
                            </p>
                        @else
                            <input type="number" name="cn_links_ttl_hours" min="1" max="720"
                                   value="{{ \App\Services\ClientPortal\PortalLinks::ttlHours() }}"
                                   class="w-full mt-1 px-3 py-2 rounded-lg border border-gray-200 text-sm">
                        @endif
                    </div>
                </div>

                @if($cnLocked)
                    <p class="text-[11px] text-gray-500 mt-2">إشعارات الموكّل يضبطها المطوّر — للتغيير راسله.</p>
                @endif

                <p class="text-[11px] text-gray-500 leading-relaxed mt-2 bg-gray-50 border border-gray-200 rounded-lg p-2.5">
                    الرابط يُستعمل <span class="font-semibold">مرّةً واحدة</span> وتنتهي صلاحيته بالمدّة أعلاه.
                    وإن أطفأته، وصل الموكّلَ رابطُ بوابةٍ عاديّ يدخل منه برقم هويّته وآخرِ ثلاثة أرقام من هاتفه.
                </p>
            </div>

            {{-- كانت هنا أربعُ خاناتٍ قديمة: ثلاثةُ أبوابِ إشعارٍ سبقت
                 المنظومةَ الموحّدة (وصارت أنواعاً داخلها — وبقاؤها
                 مفاتيحَ مستقلّةً يعني رسالتين عن الحدث الواحد)، وردٌّ
                 آليٌّ قرّر صاحبُ المنظومة إغلاقَه نصّاً: «ما أريد رداً
                 آلياً ولا شيء». أُغلقت كلُّها بهجرة، ولا تُعرض. --}}

            <div class="grid md:grid-cols-3 gap-4">
                <div>
                    <label class="text-xs font-bold text-gray-500">{{ __('app.wa_reminder_hours') }}</label>
                    <input type="number" name="wa_reminder_hours" min="1" max="72"
                           value="{{ \App\Support\WhatsAppSettings::reminderHours() }}"
                           class="w-full mt-1 px-3 py-2 rounded-lg border border-gray-200 text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="text-xs font-bold text-gray-500">{{ __('app.wa_templates') }}</label>
                    <select name="wa_session_template" class="w-full mt-1 px-3 py-2 rounded-lg border border-gray-200 text-sm">
                        <option value="">{{ __('app.wa_template_none_approved') }}</option>
                        @foreach($waTemplates as $template)
                            <option value="{{ $template->name }}"
                                @selected(\App\Support\WhatsAppSettings::templateName(\App\Support\WhatsAppSettings::KEY_SESSION_TEMPLATE) === $template->name)>
                                {{ $template->name }} ({{ $template->language }}) — {{ \Illuminate\Support\Str::limit($template->body, 50) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex items-center gap-2 flex-wrap pt-2">
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-gold-dark text-white text-sm font-bold">{{ __('app.save') }}</button>
                @if($wa['connected'])
                    <button type="submit" form="wa-sync" class="px-4 py-2.5 rounded-xl border border-gray-200 text-gray-600 text-sm font-bold">{{ __('app.wa_template_sync') }}</button>
                @endif
            </div>
        </form>

        @if($wa['connected'])
            <form id="wa-sync" method="POST" action="{{ route('settings.whatsapp.templates.sync') }}" class="hidden">@csrf</form>

            <form method="POST" action="{{ route('settings.whatsapp.disconnect') }}" class="mt-4 pt-4 border-t border-gray-100">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-xs font-bold text-red-600 hover:text-red-700"
                        data-confirm="{{ __('app.wa_disconnect_confirm_body') }}">{{ __('app.wa_disconnect_confirm_title') }}</button>
            </form>
        @endif

        <p class="mt-4 text-[11px] text-gray-400 leading-relaxed">{{ __('app.wa_pricing_note') }}</p>
    </div>


    {{-- ===== الذكاء الاصطناعي: مفتاح ونموذج خاصان بهذا المكتب ===== --}}
    @php
        $aiProviders = \App\Support\AiSettings::availableProviders();
        $aiProvider = \App\Support\AiSettings::provider();
        $aiModel = \App\Support\AiSettings::model();
        $aiMasked = \App\Support\AiSettings::maskedKey();
        $aiFromEnv = \App\Support\AiSettings::usingEnvFallback();
        $aiUpdated = \App\Support\AiSettings::updatedAt();
        $aiKeyUrl = config("ai.providers.$aiProvider.key_url");
        $aiModels = (array) config("ai.providers.$aiProvider.models", []);
        $aiHealth = \App\Support\AiHealth::snapshot();
    @endphp
    <div class="bg-white rounded-xl border border-gray-200 p-5"
         x-data="{ testing: false, result: null, ok: false,
            async test() {
                this.testing = true; this.result = null;
                try {
                    const r = await fetch('{{ route('settings.ai.test') }}', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                        credentials: 'same-origin'
                    });
                    const d = await r.json();
                    this.ok = !!d.ok; this.result = d.message || (d.ok ? 'الاتصال ناجح' : 'فشل الاتصال');
                } catch (e) {
                    this.ok = false; this.result = 'تعذّر تنفيذ الفحص. تحقق من اتصال الخادم.';
                }
                this.testing = false;
            } }">
        {{-- §88: صحة المساعد — أرقام حقيقية للإدارة، لا تظهر لغيرها --}}
        @php
            $aiTone = ['healthy' => ['نشط', 'bg-emerald-50 text-emerald-700 border-emerald-200'],
                       'warning' => ['متعثر', 'bg-yellow-50 text-yellow-700 border-yellow-200'],
                       'offline' => ['غير مهيأ', 'bg-gray-100 text-gray-500 border-gray-200']][$aiHealth['status']];
        @endphp
        <div class="mb-4 rounded-xl border border-gray-200 bg-gray-50 p-4 flex flex-wrap items-center gap-x-6 gap-y-2 text-xs">
            <span class="px-2.5 py-1 rounded-lg border font-bold {{ $aiTone[1] }}">{{ $aiTone[0] }}</span>
            <span class="text-gray-500">النموذج: <b class="text-gray-700">{{ $aiHealth['model'] }}</b></span>
            <span class="text-gray-500">آخر نجاح:
                <b class="text-gray-700">{{ $aiHealth['last_success_at'] ? \Illuminate\Support\Carbon::parse($aiHealth['last_success_at'])->diffForHumans() : 'لم يُستعمل بعد' }}</b>
            </span>
            @if($aiHealth['last_error'])
                <span class="text-gray-500">آخر خطأ:
                    <b class="text-red-600">{{ $aiHealth['last_error']['type'] ?? '—' }}</b>
                    <span class="text-gray-400">({{ ($aiHealth['last_error']['at'] ?? null) ? \Illuminate\Support\Carbon::parse($aiHealth['last_error']['at'])->diffForHumans() : '' }})</span>
                </span>
            @endif
            <span class="text-gray-500">اليوم: <b class="text-gray-700">{{ $aiHealth['counts']['today'] }}</b> طلبًا
                @if($aiHealth['counts']['today_errors'] > 0)<b class="text-red-600">({{ $aiHealth['counts']['today_errors'] }} خطأ)</b>@endif
            </span>
            <span class="text-gray-500">هذا الشهر: <b class="text-gray-700">{{ $aiHealth['counts']['month'] }}</b></span>
            @if($aiHealth['avg_ms'] !== null)
                <span class="text-gray-500">متوسط الرد:
                    <b class="{{ $aiHealth['avg_ms'] > 15000 ? 'text-red-600' : ($aiHealth['avg_ms'] > 8000 ? 'text-amber-600' : 'text-gray-700') }}">{{ number_format($aiHealth['avg_ms'] / 1000, 1) }} ث</b>
                    @if($aiHealth['last_ms'] !== null)<span class="text-gray-400">(آخر طلب {{ number_format($aiHealth['last_ms'] / 1000, 1) }} ث)</span>@endif
                </span>
            @endif
        </div>

        <div class="flex items-center gap-3 mb-1">
            <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-gold-light to-gold-dark flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z"/>
                </svg>
            </div>
            <div class="flex-1">
                <h2 class="text-base font-bold text-gray-800">الذكاء الاصطناعي</h2>
                <p class="text-xs text-gray-500 mt-0.5">
                    @if($aiFromEnv)
                        يعمل مكتبك بمفتاح مُداوَلة المركزي — جاهزٌ بلا إعداد، ولك أن تضع مفتاحك الخاص متى شئت.
                    @else
                        يعمل المساعد بحساب مُداوَلة المركزي. ومفتاحُ مكتبك — إن وضعتَه — احتياطٌ يُلجأ إليه، ومحادثاتُ مكتبك في قاعدته وحدها لا يراها غيرُه.
                    @endif
                </p>
            </div>
            @if($aiMasked)
                <span class="text-[11px] font-bold px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 whitespace-nowrap">{{ $aiFromEnv ? 'يعمل — مفتاح مُداوَلة' : 'مُعدّ ✓' }}</span>
            @else
                <span class="text-[11px] font-bold px-2.5 py-1 rounded-full bg-gray-100 text-gray-500 border border-gray-200 whitespace-nowrap">غير مُعدّ</span>
            @endif
        </div>

        @if($aiFromEnv)
            <div class="mt-4 flex items-start gap-2 text-[12px] leading-relaxed bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-3 py-2.5">
                <span class="font-bold">✓</span>
                <span>مساعدك يعمل بمفتاح مُداوَلة المركزي — لا إعداد مطلوب منك. وإن فضّلت مفتاحاً خاصاً بمكتبك (حصة واستهلاك مستقلَّان تماماً) فأضفه هنا، وسيتقدّم على المركزي فوراً.</span>
            </div>
        @endif

        <form method="POST" action="{{ route('settings.ai.update') }}" class="mt-5 space-y-4">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="ai_provider" class="block text-sm font-medium text-gray-700 mb-2">المزوّد</label>
                    <select id="ai_provider" name="ai_provider" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-gold/40 focus:border-gold outline-none">
                        @foreach($aiProviders as $key => $cfg)
                            <option value="{{ $key }}" @selected($aiProvider === $key)>{{ $cfg['label'] }}</option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-gray-400 mt-1.5">تُعرض هنا المزوّدات المدعومة فعلياً فقط.</p>
                    @error('ai_provider')<p class="text-xs text-red-600 font-semibold mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="ai_model" class="block text-sm font-medium text-gray-700 mb-2">النموذج</label>
                    <select id="ai_model" name="ai_model" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-gold/40 focus:border-gold outline-none">
                        @foreach($aiModels as $m)
                            <option value="{{ $m }}" @selected($aiModel === $m)>{{ $m }}</option>
                        @endforeach
                    </select>
                    @error('ai_model')<p class="text-xs text-red-600 font-semibold mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label for="ai_api_key" class="block text-sm font-medium text-gray-700 mb-2">مفتاح الـ API</label>
                @if($aiMasked)
                    <div class="flex items-center gap-2 mb-2">
                        <code class="text-sm font-mono tracking-wider text-gray-600 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2" dir="ltr">{{ $aiMasked }}</code>
                        @if($aiUpdated)
                            <span class="text-[11px] text-gray-400">حُدّث في {{ $aiUpdated }}</span>
                        @endif
                    </div>
                @endif
                <input type="password" id="ai_api_key" name="ai_api_key" dir="ltr" autocomplete="off"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm font-mono focus:ring-2 focus:ring-gold/40 focus:border-gold outline-none"
                       placeholder="{{ $aiMasked ? 'اتركه فارغاً للإبقاء على المفتاح الحالي، أو ألصق مفتاحاً جديداً لاستبداله' : (config("ai.providers.$aiProvider.key_prefix_hint") ?? 'ألصق المفتاح هنا') }}">
                <p class="text-[11px] text-gray-400 mt-1.5">
                    يُخزَّن مشفَّراً في قاعدة بيانات مكتبك ولا يُعرض بعد الحفظ.
                    @if($aiKeyUrl)
                        <a href="{{ $aiKeyUrl }}" target="_blank" rel="noopener" class="text-gold-dark font-semibold hover:underline">أنشئ مفتاحاً</a>
                    @endif
                </p>
                @error('ai_api_key')<p class="text-xs text-red-600 font-semibold mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-wrap items-center gap-2 pt-1">
                <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-5 py-2.5 rounded-lg font-semibold transition-colors text-sm">
                    حفظ الإعدادات
                </button>

                <button type="button" x-on:click="test()" :disabled="testing"
                        class="inline-flex items-center gap-2 border border-gray-300 text-gray-700 hover:border-gold hover:text-gold-dark px-4 py-2.5 rounded-lg font-semibold text-sm transition disabled:opacity-60 disabled:cursor-not-allowed">
                    <svg x-show="testing" x-cloak class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" opacity="0.25"/>
                        <path d="M21 12a9 9 0 00-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                    </svg>
                    <span x-text="testing ? 'جارٍ الفحص…' : 'اختبار الاتصال'"></span>
                </button>
            </div>

            <div x-show="result" x-cloak x-transition
                 class="text-sm font-semibold rounded-lg px-3.5 py-2.5 border"
                 :class="ok ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-red-50 text-red-800 border-red-200'">
                <span x-text="ok ? '✓ ' : '✕ '"></span><span x-text="result"></span>
            </div>
        </form>

        @if($aiMasked && !$aiFromEnv)
            <form method="POST" action="{{ route('settings.ai.destroy') }}" class="mt-4 pt-4 border-t border-gray-100"
                  data-confirm="حذف مفتاح الذكاء الاصطناعي؟ ستتوقف ميزات الذكاء الاصطناعي في مكتبك حتى تضبط مفتاحاً جديداً.">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-xs font-bold text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 px-3 py-1.5 rounded-lg transition">
                    حذف المفتاح
                </button>
            </form>
        @endif
    </div>

    <form method="POST" action="{{ route('settings.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-6">
            <h2 class="text-lg font-semibold text-gold-dark border-b border-gray-200 pb-3">{{ __('app.office_info') }}</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="office_name" class="block text-sm font-medium text-gray-700 mb-2">{{ __('app.office_name') }}</label>
                    <input
                        type="text"
                        name="office_name"
                        id="office_name"
                        value="{{ old('office_name', $settings['office_name'] ?? '') }}"
                        class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-gold-dark focus:border-gold/40"
                    >
                    @error('office_name')
                        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-400">يظهر مُرسِلاً في كل رسالة تصل موكّليك.</p>
                </div>
                <div>
                    <label for="office_email" class="block text-sm font-medium text-gray-700 mb-2">{{ __('app.office_email') }}</label>
                    <input
                        type="email"
                        name="office_email"
                        id="office_email"
                        value="{{ old('office_email', $settings['office_email'] ?? '') }}"
                        class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-gold-dark focus:border-gold/40"
                    >
                    @error('office_email')
                        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-400">وجهةُ الردّ: حين يردّ الموكّل على رسالته يصل ردُّه هنا.</p>
                </div>
                <div>
                    <label for="office_phone" class="block text-sm font-medium text-gray-700 mb-2">{{ __('app.office_phone') }}</label>
                    <input
                        type="text"
                        name="office_phone"
                        id="office_phone"
                        value="{{ old('office_phone', $settings['office_phone'] ?? '') }}"
                        class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-gold-dark focus:border-gold/40"
                        dir="ltr"
                    >
                    @error('office_phone')
                        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="office_address" class="block text-sm font-medium text-gray-700 mb-2">{{ __('app.office_address') }}</label>
                    <input
                        type="text"
                        name="office_address"
                        id="office_address"
                        value="{{ old('office_address', $settings['office_address'] ?? '') }}"
                        class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-gold-dark focus:border-gold/40"
                    >
                    @error('office_address')
                        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- بريد الموكّلين: ما الذي يصلهم فعلاً --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
            <div class="border-b border-gray-200 pb-3">
                <h2 class="text-lg font-semibold text-gold-dark">بريد الموكّلين</h2>
                <p class="text-xs text-gray-400 mt-1">
                    يُرسَل من بريد مُداوَلة المركزي باسم مكتبك، والردّ يصل إلى بريد المكتب.
                </p>
            </div>

            {{--
                بطاقةُ الهوية.

                اسمُ المكتب وبريدُه حقلان في «معلومات المكتب» أعلاه، ولا
                يبدو منهما أنّهما يحكمان ما يقرؤه الموكّل في رسالته. فتُعرض
                الترويسةُ هنا كما ستخرج فعلاً — من MailIdentity نفسها التي
                يقرأ منها الإرسال، فلا تعِد الشاشةُ بشيءٍ ويخرج البريد بغيره.
            --}}
            @php
                $mailIdentity = \App\Support\MailIdentity::clientSees();
                $mailIssues = \App\Support\MailIdentity::identityIssues();
            @endphp

            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                <p class="text-xs font-semibold text-gray-500 mb-3">ما يظهر للموكّل في رسالته</p>
                <dl class="space-y-2.5">
                    <div class="flex items-baseline gap-3">
                        <dt class="w-28 shrink-0 text-xs text-gray-500">المُرسِل</dt>
                        <dd class="text-sm font-semibold text-gray-900">{{ $mailIdentity['name'] }}</dd>
                    </div>
                    <div class="flex items-baseline gap-3">
                        <dt class="w-28 shrink-0 text-xs text-gray-500">العنوان</dt>
                        <dd class="text-xs text-gray-500" dir="ltr">{{ $mailIdentity['address'] }}</dd>
                    </div>
                    <div class="flex items-baseline gap-3">
                        <dt class="w-28 shrink-0 text-xs text-gray-500">يصل الردّ إلى</dt>
                        <dd class="text-xs">
                            @if($mailIdentity['replyTo'])
                                <span class="text-gray-900 font-medium" dir="ltr">{{ $mailIdentity['replyTo'] }}</span>
                            @else
                                <span class="text-red-700 font-medium">الصندوق المركزي — لا يراه أحدٌ في مكتبك</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

            @if($mailIssues)
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 space-y-2" role="alert">
                    @foreach($mailIssues as $mailIssue)
                        <p class="text-xs text-amber-900 leading-relaxed">
                            {{ $mailIssue['text'] }}
                            <a href="#{{ $mailIssue['key'] }}"
                               class="font-bold underline decoration-amber-400 underline-offset-2 hover:text-amber-950">
                                اضبطه في «معلومات المكتب»
                            </a>
                        </p>
                    @endforeach
                </div>
            @endif

            <input type="hidden" name="mail_kinds_section" value="1">
            <div class="space-y-4">
                @foreach(\App\Mail\MailKind::all() as $mailKind)
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input
                            type="checkbox"
                            name="{{ $mailKind->settingKey() }}"
                            value="1"
                            {{ old($mailKind->settingKey(), $mailKind->isEnabled()) ? 'checked' : '' }}
                            class="w-4 h-4 mt-0.5 text-gold-dark bg-white border-gray-300 rounded focus:ring-gold-dark"
                        >
                        <span class="text-gray-700">{{ $mailKind->label() }}</span>
                    </label>
                @endforeach
            </div>
            @if(!\App\Support\MailIdentity::isConfigured())
                <p class="text-xs text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2" role="alert">
                    البريد غير مُفعَّل على هذا الخادم — لن تصل أي رسالة حتى يُضبط. راجع الدعم.
                </p>
            @endif
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-6">
            <h2 class="text-lg font-semibold text-gold-dark border-b border-gray-200 pb-3">{{ __('app.notification_settings') }}</h2>
            <div class="space-y-4">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input
                        type="checkbox"
                        name="email_notifications"
                        value="1"
                        {{ old('email_notifications', $settings['email_notifications'] ?? true) ? 'checked' : '' }}
                        class="w-4 h-4 text-gold-dark bg-white border-gray-300 rounded focus:ring-gold-dark"
                    >
                    <span class="text-gray-700">{{ __('app.email_notifications') }}</span>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input
                        type="checkbox"
                        name="task_reminders"
                        value="1"
                        {{ old('task_reminders', $settings['task_reminders'] ?? true) ? 'checked' : '' }}
                        class="w-4 h-4 text-gold-dark bg-white border-gray-300 rounded focus:ring-gold-dark"
                    >
                    <span class="text-gray-700">{{ __('app.task_reminders') }}</span>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input
                        type="checkbox"
                        name="deadline_alerts"
                        value="1"
                        {{ old('deadline_alerts', $settings['deadline_alerts'] ?? true) ? 'checked' : '' }}
                        class="w-4 h-4 text-gold-dark bg-white border-gray-300 rounded focus:ring-gold-dark"
                    >
                    <span class="text-gray-700">{{ __('app.deadline_alerts') }}</span>
                </label>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-6">
            <h2 class="text-lg font-semibold text-gold-dark border-b border-gray-200 pb-3">{{ __('app.system_settings') }}</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('app.timezone') }}</label>
                    <input type="text" value="{{ __('app.oman_muscat') }}" disabled
                        class="w-full rounded-lg bg-white border border-gray-200 text-gray-500 rounded-lg px-4 py-2.5 cursor-not-allowed">
                    <p class="mt-1 text-xs text-gray-400">{{ __('app.oman_only') }}</p>
                </div>
                <div>
                    <label for="date_format" class="block text-sm font-medium text-gray-700 mb-2">{{ __('app.date_format') }}</label>
                    <select
                        name="date_format"
                        id="date_format"
                        class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-gold-dark focus:border-gold/40"
                    >
                        <option value="Y-m-d" {{ old('date_format', $settings['date_format'] ?? 'Y-m-d') === 'Y-m-d' ? 'selected' : '' }}>2026-07-14</option>
                        <option value="d/m/Y" {{ old('date_format', $settings['date_format'] ?? '') === 'd/m/Y' ? 'selected' : '' }}>14/07/2026</option>
                        <option value="d-m-Y" {{ old('date_format', $settings['date_format'] ?? '') === 'd-m-Y' ? 'selected' : '' }}>14-07-2026</option>
                    </select>
                    @error('date_format')
                        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="items_per_page" class="block text-sm font-medium text-gray-700 mb-2">{{ __('app.items_per_page') }}</label>
                    <input
                        type="number"
                        name="items_per_page"
                        id="items_per_page"
                        value="{{ old('items_per_page', $settings['items_per_page'] ?? 15) }}"
                        min="5"
                        max="100"
                        class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-gold-dark focus:border-gold/40"
                    >
                    @error('items_per_page')
                        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- ═══ أوقات المواعيد ═══

             شاشةُ الحجز تعرض الفُسَحَ من هذه القيم وحدها: بلا يوم عملٍ
             واحدٍ لا تُعرض فُسحةٌ أبداً وتبدو الشاشةُ معطّلة، فالغيابُ
             الكاملُ للأيّام يعود إلى أسبوع العمل الافتراضي. --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
            <input type="hidden" name="appt_section" value="1">

            <h2 class="text-lg font-bold text-gray-900 mb-1">المواعيد</h2>
            <p class="text-sm text-gray-500 mb-5">
                أوقاتُ الدوام التي تُعرض منها الفُسَح، وطولُ الموعد، ومتى يُذكَّر الموكّل قبله.
            </p>

            <div class="mb-5">
                <span class="block text-sm font-semibold text-gray-800 mb-2">أيّام العمل</span>
                <div class="flex flex-wrap gap-2">
                    @php $activeDays = \App\Support\AppointmentSlots::days(); @endphp
                    @foreach(\App\Support\AppointmentSlots::DAY_NAMES as $num => $name)
                        <label class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer text-sm
                                      {{ in_array($num, $activeDays, true) ? 'border-gold/50 bg-gold/5 text-gray-800' : 'border-gray-200 text-gray-500' }}">
                            <input type="checkbox" name="appt_days[]" value="{{ $num }}"
                                   @checked(in_array($num, $activeDays, true))
                                   class="rounded border-gray-300 text-gold focus:ring-gold">
                            <span>{{ $name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="grid md:grid-cols-4 gap-4">
                <div>
                    <label for="appt_start" class="block text-sm font-semibold text-gray-800 mb-1.5">بداية الدوام</label>
                    <input id="appt_start" type="time" name="appt_start"
                           value="{{ \App\Support\AppointmentSlots::startTime() }}"
                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="appt_end" class="block text-sm font-semibold text-gray-800 mb-1.5">نهاية الدوام</label>
                    <input id="appt_end" type="time" name="appt_end"
                           value="{{ \App\Support\AppointmentSlots::endTime() }}"
                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="appt_slot_minutes" class="block text-sm font-semibold text-gray-800 mb-1.5">طول الفُسحة (دقيقة)</label>
                    <input id="appt_slot_minutes" type="number" name="appt_slot_minutes" min="5" max="240" step="5"
                           value="{{ \App\Support\AppointmentSlots::slotMinutes() }}"
                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="appt_remind_hours" class="block text-sm font-semibold text-gray-800 mb-1.5">التذكير قبل (ساعة)</label>
                    <input id="appt_remind_hours" type="number" name="appt_remind_hours" min="1" max="168"
                           value="{{ \App\Support\AppointmentSlots::remindHours() }}"
                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                </div>
            </div>

            <p class="text-xs text-gray-500 mt-4">
                وصولُ رسالة الموعد إلى الموكّل يحكمه قسمُ «إشعارات الموكّل» أعلاه — واتساباً وبريداً.
            </p>
        </div>

        {{-- الحضور والصيانة: إعدادان كانا يُقرآن ولا تكتبهما واجهة — فبقي
             الحضور التلقائي مفروضاً بلا مفتاح إطفاء، وملاحظةُ الصيانة لا
             تظهر أبداً مهما احتاجها المكتب. --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
            <input type="hidden" name="hr_section" value="1">

            <h2 class="text-lg font-bold text-gray-900 mb-1">الحضور والصيانة</h2>
            <p class="text-sm text-gray-500 mb-5">ضبط تسجيل الحضور التلقائي ونصّ صفحة الصيانة.</p>

            <label class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 hover:bg-gray-50 cursor-pointer mb-5">
                <input type="checkbox" name="hr_auto_checkin" value="1"
                       @checked(\App\Support\AttendanceGuard::autoEnabled())
                       class="mt-0.5 w-4 h-4 rounded text-gold focus:ring-gold/40 border-gray-300">
                <span>
                    <span class="block text-sm font-semibold text-gray-800">تسجيل الحضور تلقائياً عند الدخول</span>
                    <span class="block text-xs text-gray-500 mt-0.5">
                        يُسجَّل مرّةً واحدة في اليوم مهما تكرّر الدخول. أطفئه إن كان المكتب
                        يسجّل الحضور بوسيلةٍ أخرى.
                    </span>
                </span>
            </label>

            <label class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 hover:bg-gray-50 cursor-pointer mb-5">
                <input type="checkbox" name="hr_auto_close" value="1"
                       @checked(\App\Support\AttendanceGuard::autoCloseEnabled())
                       class="mt-0.5 w-4 h-4 rounded text-gold focus:ring-gold/40 border-gray-300">
                <span>
                    <span class="block text-sm font-semibold text-gray-800">إقفال ليليّ للسجلّات التي نُسي انصرافها</span>
                    <span class="block text-xs text-gray-500 mt-0.5">
                        معطَّلٌ افتراضاً: الانصراف يُسجَّل بضغط زرّ الخروج وحده، ومن نسيه
                        تُعرض خانتُه «بلا انصراف» ليُصحّحها الإداري. فعّله إن أردت أن يُقفل
                        النظام السجلَّ المنسيّ آخرَ الليل على آخر نشاطٍ معروف — مع وسمه
                        بأن الوقت مستنتَجٌ لا مسجَّل.
                    </span>
                </span>
            </label>

            <div class="mb-5">
                <label for="hr_shift_cap_hours" class="block text-sm font-semibold text-gray-800 mb-1.5">سقف المناوبة (ساعة)</label>
                <input id="hr_shift_cap_hours" type="number" name="hr_shift_cap_hours" min="1" max="24"
                       value="{{ \App\Support\AttendanceGuard::capHours() }}"
                       class="w-32 px-3 py-2 rounded-lg border border-gray-200 text-sm">
                <p class="text-xs text-gray-500 mt-1.5 leading-relaxed">
                    من مضى على حضوره هذا العدد بلا انصراف يُقفل سجلُّه على «حضورٌ + المدّة»،
                    ويُوسم بأنّ الوقت بلغ السقف لا أنّ صاحبَه ضغطه. حدٌّ معلومٌ مقدَّماً لا
                    استنتاجٌ من آخر نشاط — ولولاه بقي سجلُّ من أغلق المتصفّح مفتوحاً أياماً.
                </p>
            </div>

            <div>
                <label for="maintenance_note" class="block text-sm font-semibold text-gray-800 mb-1.5">ملاحظة صفحة الصيانة</label>
                <textarea id="maintenance_note" name="maintenance_note" rows="2" maxlength="300"
                          placeholder="نصٌّ يظهر أثناء الصيانة — اتركه فارغاً لإخفائه"
                          class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-gold/40 focus:border-gold outline-none">{{ \App\Models\Setting::get('maintenance_note') }}</textarea>
                @error('maintenance_note')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- بوابة العملاء --}}
        @php $cp = \App\Support\ClientPortal::class; @endphp
        <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
            <input type="hidden" name="client_portal_section" value="1">

            <div class="flex items-start justify-between gap-4 mb-1">
                <h2 class="text-lg font-bold text-gray-900">بوابة العملاء</h2>
                <a href="{{ route('client.access') }}" target="_blank" rel="noopener"
                   class="text-xs font-semibold text-gold-dark hover:underline shrink-0">فتح البوابة ↗</a>
            </div>
            <p class="text-sm text-gray-500 mb-5">
                صفحة يدخل إليها عميلك برقم هويته ليتابع قضاياه. تُدار من هنا، وما لا تُفعّله لا يظهر له.
            </p>

            <label class="flex items-start gap-3 mb-5 cursor-pointer">
                <input type="checkbox" name="{{ $cp::KEY_ENABLED }}" value="1" class="mt-1 rounded"
                       @checked(\App\Support\ClientPortal::enabled())>
                <span>
                    <span class="block text-sm font-semibold text-gray-800">تفعيل البوابة</span>
                    <span class="block text-xs text-gray-500">عند التعطيل تُغلق الصفحة تماماً ولا يستطيع أي عميل الدخول.</span>
                </span>
            </label>

            <div class="mb-5">
                <label for="client_portal_welcome" class="block text-sm font-medium text-gray-700 mb-2">رسالة الترحيب</label>
                <input type="text" name="client_portal_welcome" id="client_portal_welcome" maxlength="300"
                       value="{{ old('client_portal_welcome', $settings['client_portal_welcome'] ?? '') }}"
                       placeholder="تظهر للعميل في صفحة الدخول — اتركها فارغة للنص الافتراضي"
                       class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-gold-dark focus:border-gold/40">
            </div>

            <p class="text-sm font-semibold text-gray-800 mb-3">ما يستطيع العميل رؤيته</p>
            <div class="grid sm:grid-cols-2 gap-3">
                @foreach ([
                    [$cp::KEY_SHOW_SESSIONS, 'الجلسات', 'مواعيد جلسات قضاياه وحالتها'],
                    [$cp::KEY_SHOW_TIMELINE, 'مسار القضية', 'أحداث القضية — دون الملاحظات الداخلية'],
                    [$cp::KEY_SHOW_DOCUMENTS, 'المستندات', 'المستندات التي علّمتها «مرئية للعميل» فقط'],
                    [$cp::KEY_SHOW_LAWYER, 'المحامي المسؤول', 'اسم المحامي المكلَّف بقضيته'],
                    [$cp::KEY_SHOW_OPPONENT, 'بيانات الخصم', 'اسم الخصم كما هو مسجَّل في القضية'],
                    [$cp::KEY_SHOW_ACCOUNTING, 'المحاسبة', 'الرسوم والفواتير التي علّمتها «مرئية للموكّل» فقط'],
                ] as [$key, $label, $hint])
                    <label class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 cursor-pointer hover:border-gold/40">
                        <input type="checkbox" name="{{ $key }}" value="1" class="mt-1 rounded"
                               @checked(\App\Support\ClientPortal::flag($key))>
                        <span>
                            <span class="block text-sm font-semibold text-gray-800">{{ $label }}</span>
                            <span class="block text-xs text-gray-500">{{ $hint }}</span>
                        </span>
                    </label>
                @endforeach
            </div>

            <p class="mt-4 text-xs text-gray-500 leading-relaxed bg-gray-50 border border-gray-200 rounded-lg p-3">
                لا يُعرض للعميل أي مستند إلا إذا فعّلت «المستندات» <span class="font-semibold">و</span> علّمت المستند نفسه
                «مرئي للعميل» من صفحة القضية. والمستندات الخاصة لا تظهر له مهما كان.
            </p>
        </div>

        <div class="flex items-center gap-4">
            <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">{{ __('app.save_settings') }}</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce }}">
(function () {
    // ═══ لماذا كان هذا الملفّ بلا جافاسكربت ═══
    //
    // كانت أزرارُ «اختبار الاتصال» و«نسخ» موجودةً بسِماتها
    // ‏(data-wa-test و data-wa-copy) ولا شيءَ يستمع إليها. فيضغطها
    // صاحبُ المكتب ولا يحدث شيء — لا رسالةَ نجاحٍ ولا خطأ — فيظنّ
    // النظامَ معطّلاً ويعيد إدخال بياناته من أوّلها.
    'use strict';

    var card = document.getElementById('whatsapp-settings');
    if (!card) { return; }

    var token = document.querySelector('meta[name="csrf-token"]');
    var csrf = token ? token.getAttribute('content') : '';

    function flash(button, text, good) {
        var original = button.dataset.waOriginal || button.textContent;
        button.dataset.waOriginal = original;
        button.textContent = text;
        button.classList.toggle('text-emerald-700', !!good);
        button.classList.toggle('text-red-700', !good);
        setTimeout(function () {
            button.textContent = button.dataset.waOriginal;
            button.classList.remove('text-emerald-700', 'text-red-700');
        }, 2200);
    }

    // ── النسخ ────────────────────────────────────────────────
    // ‏clipboard.writeText يحتاج سياقاً آمناً؛ ولأنّ بعض المكاتب
    // تُفتح على http في شبكةٍ داخلية، يبقى تحديدُ النصّ بديلاً
    // يعمل في كلّ حال بدل زرٍّ صامت.
    card.querySelectorAll('[data-wa-copy]').forEach(function (button) {
        button.addEventListener('click', function () {
            var value = button.getAttribute('data-wa-copy') || '';

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(value).then(function () {
                    flash(button, 'نُسخ', true);
                }).catch(function () {
                    selectSibling(button);
                });
            } else {
                selectSibling(button);
            }
        });
    });

    function selectSibling(button) {
        var input = button.parentElement && button.parentElement.querySelector('input');
        if (input) { input.select(); flash(button, 'حدِّد وانسخ', true); }
    }

    // ── اختبار الاتصال ───────────────────────────────────────
    var testButton = card.querySelector('[data-wa-test]');
    if (testButton) {
        testButton.addEventListener('click', function () {
            testButton.disabled = true;
            flash(testButton, 'جارٍ الفحص…', true);

            fetch(@json(route('settings.whatsapp.test')), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                credentials: 'same-origin'
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                flash(testButton, data.ok ? 'الاتصال سليم' : 'أخفق', !!data.ok);
                if (!data.ok && data.message) { alert(data.message); }
            })
            .catch(function () { flash(testButton, 'تعذّر الفحص', false); })
            .finally(function () { testButton.disabled = false; });
        });
    }

    // ── اقتران الجسر بمسح رمز ────────────────────────────────
    //
    // الشاشةُ تسأل الحالةَ كل ثانيتين لا تنتظر ويبهوكاً: الويبهوك
    // يصل فعلاً، لكنّ المكتب ينظر إلى الشاشة لا إلى السجلّ — ولو
    // انتظرناه وحده لبقي الرمزُ معروضاً بعد نجاح المسح، فيعيد
    // المكتبُ المسحَ ظنّاً أنّه أخفق.
    var pairBox = card.querySelector('[data-wa-pair]');

    if (pairBox) {
        var qrBox = pairBox.querySelector('[data-pair-qr-box]');
        var badge = pairBox.querySelector('[data-pair-badge]');
        var status = pairBox.querySelector('[data-pair-status]');
        var start = pairBox.querySelector('[data-pair-start]');
        var poll = null;

        var phoneToggle = pairBox.querySelector('[data-pair-phone-toggle]');
        var phoneBox = pairBox.querySelector('[data-pair-phone-box]');
        var phoneInput = pairBox.querySelector('[data-pair-phone]');
        var phoneStart = pairBox.querySelector('[data-pair-phone-start]');
        var codeBox = pairBox.querySelector('[data-pair-code]');

        start.addEventListener('click', function () { request(null, start, 'أعد إحضار الرمز'); });

        if (phoneToggle) {
            phoneToggle.addEventListener('click', function () {
                phoneBox.classList.toggle('hidden');
                if (!phoneBox.classList.contains('hidden')) { phoneInput.focus(); }
            });

            phoneStart.addEventListener('click', function () {
                var digits = (phoneInput.value || '').replace(/\D+/g, '');

                if (digits.length < 10) {
                    setStatus('اكتب الرقم بصيغته الدولية بلا صفرٍ ولا زائد — مثال: 96891234567', 'bad');

                    return;
                }

                request(digits, phoneStart, 'اطلب رمزاً جديداً');
            });
        }

        // طلبٌ واحدٌ للبابين: الفرقُ رقمٌ يُرسَل أو لا يُرسَل
        function request(phone, button, doneLabel) {
            button.disabled = true;
            button.textContent = 'جارٍ التحضير…';
            setStatus(phone ? 'يُطلب رمزُ الربط من واتساب…' : 'يُنشأ الاتصال بالخادم…', 'wait');

            fetch(pairBox.dataset.pairUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({ phone: phone || '' })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.state === 'open') { connected(); return; }

                if (data.code) { showCode(data.code); startPolling(); return; }
                if (data.qr) { showQr(data.qr); startPolling(); return; }

                setStatus(data.message || 'تعذّر إحضار الرمز.', 'bad');
            })
            .catch(function () { setStatus('تعذّر الاتصال بالخادم.', 'bad'); })
            .finally(function () {
                button.disabled = false;
                button.textContent = doneLabel;
            });
        }

        function showCode(code) {
            codeBox.textContent = code;
            codeBox.classList.remove('hidden');
            phoneBox.classList.remove('hidden');
            setStatus('اكتب هذا الرمز في هاتفك خلال دقيقة — بعدها اطلب غيره.', 'wait');
        }

        function showQr(qr) {
            qrBox.textContent = '';

            if (qr.indexOf('data:') === 0) {
                var img = document.createElement('img');
                img.src = qr;
                img.alt = 'رمز الاقتران';
                img.className = 'w-full h-full object-contain';
                qrBox.appendChild(img);
            } else {
                // بعضُ إصدارات الجسر تُرجع نصَّ الرمز لا صورتَه
                var pre = document.createElement('div');
                pre.className = 'text-[9px] font-mono break-all p-2 text-gray-600';
                pre.textContent = qr.replace(/^text:/, '');
                qrBox.appendChild(pre);
            }

            setStatus('امسح الرمز خلال دقيقة — بعدها يُعاد إحضاره.', 'wait');
        }

        function startPolling() {
            if (poll) { clearInterval(poll); }

            var ticks = 0;

            poll = setInterval(function () {
                ticks++;

                // ‏١٥٠ ثانية ثمّ نتوقّف: صفحةٌ تُركت مفتوحةً ليلاً لا
                // تسأل الخادمَ ألفَ مرّة بلا أحدٍ ينظر
                if (ticks > 75) { clearInterval(poll); setStatus('انتهت المهلة — اضغط لإحضار رمزٍ جديد.', 'bad'); return; }

                fetch(pairBox.dataset.stateUrl, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin'
                })
                .then(function (r) { return r.json(); })
                .then(function (data) { if (data.state === 'open') { clearInterval(poll); connected(); } })
                .catch(function () {});
            }, 2000);
        }

        function connected() {
            qrBox.textContent = '';
            var ok = document.createElement('div');
            ok.className = 'text-center text-emerald-700 font-bold text-sm px-4';
            ok.textContent = '✓ تمّ الاقتران';
            qrBox.appendChild(ok);

            badge.textContent = '● موصول';
            badge.className = 'text-[11px] px-2 py-0.5 rounded-full border bg-emerald-50 text-emerald-700 border-emerald-200';

            setStatus('الرقم موصول. أرسل رسالةً إليه من هاتفٍ آخر لتراها في صندوق الوارد.', 'good');
        }

        function setStatus(text, kind) {
            status.textContent = text;
            status.className = 'rounded-lg p-2 mt-2 border ' + (
                kind === 'good' ? 'text-emerald-700 bg-emerald-50 border-emerald-200'
                : kind === 'bad' ? 'text-red-700 bg-red-50 border-red-200'
                : 'text-amber-700 bg-amber-50 border-amber-200'
            );
        }
    }

    // ── معالج الربط ──────────────────────────────────────────
    var checkup = card.querySelector('[data-wa-checkup]');
    var note = card.querySelector('[data-wa-wizard-note]');

    if (!checkup) { return; }

    checkup.addEventListener('click', function () {
        checkup.disabled = true;
        checkup.textContent = 'جارٍ سؤال Meta…';

        fetch(@json(route('settings.whatsapp.checkup')), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
        .then(function (r) { return r.json(); })
        .then(function (data) { paint(data); })
        .catch(function () {
            if (note) { note.textContent = 'تعذّر الفحص — أعد المحاولة'; }
        })
        .finally(function () {
            checkup.disabled = false;
            checkup.textContent = 'افحص الآن';
        });
    });

    // ── أكمل الربط تلقائياً ──────────────────────────────────
    var autowire = card.querySelector('[data-wa-autowire]');
    var result = card.querySelector('[data-wa-autowire-result]');

    if (autowire) {
        autowire.addEventListener('click', function () {
            autowire.disabled = true;
            autowire.textContent = 'جارٍ الإتمام…';

            fetch(@json(route('settings.whatsapp.autowire')), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                credentials: 'same-origin'
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.report) { paint(data.report); }
                showResult(data);
            })
            .catch(function () {
                showResult({ ok: false, failed: ['تعذّر الاتصال بالخادم — أعد المحاولة.'] });
            })
            .finally(function () {
                autowire.disabled = false;
                autowire.textContent = 'أكمل الربط تلقائياً';
            });
        });
    }

    function showResult(data) {
        if (!result) { return; }

        result.textContent = '';
        result.className = 'px-4 py-3 border-t text-xs leading-relaxed ' +
            (data.ok ? 'border-emerald-100 bg-emerald-50 text-emerald-800'
                     : 'border-amber-100 bg-amber-50 text-amber-800');

        // ‏ما تمّ وما لم يتمّ يُعرضان معاً: إخفاقُ خطوةٍ لا يعني أنّ
        // ما قبلها لم يقع، ورؤيةُ «أخفق» وحدها تدفع المكتبَ إلى
        // إعادة كلّ شيء من أوّله بلا داعٍ.
        [['✓ ', data.done || []], ['✗ ', data.failed || []]].forEach(function (pair) {
            pair[1].forEach(function (line) {
                var row = document.createElement('div');
                // ‏textContent: نصُّ الإخفاق يحمل ما تقوله Meta
                row.textContent = pair[0] + line;
                result.appendChild(row);
            });
        });

        if (data.message) {
            var only = document.createElement('div');
            only.textContent = data.message;
            result.appendChild(only);
        }
    }

    function paint(data) {
        (data.steps || []).forEach(function (step, index) {
            var li = card.querySelector('[data-wa-step="' + step.key + '"]');
            if (!li) { return; }

            var badge = li.querySelector('[data-wa-step-badge]');
            var reason = li.querySelector('[data-wa-step-reason]');
            var action = li.querySelector('[data-wa-step-action]');

            if (badge) {
                badge.className = 'mt-0.5 w-5 h-5 shrink-0 rounded-full text-[10px] font-bold flex items-center justify-center ' +
                    (step.state === 'done' ? 'bg-emerald-100 text-emerald-700'
                     : step.state === 'next' ? 'bg-amber-100 text-amber-700'
                     : 'bg-gray-100 text-gray-400');
                badge.textContent = step.state === 'done' ? '✓' : String(index + 1);
            }

            // ‏textContent لا innerHTML: نصُّ السبب يحمل ما تقوله Meta،
            // وهو نصٌّ خارجيّ لا نملك تحريره.
            if (reason) { reason.textContent = step.reason || ''; }

            if (action) {
                action.textContent = step.action || '';
                action.classList.toggle('hidden', !step.action);
            }
        });

        if (note) {
            note.textContent = data.ready
                ? 'كلّ الخطوات اللازمة تمّت — الاستقبال يعمل'
                : 'فُحص الآن';
            note.className = 'text-[11px] ' + (data.ready ? 'text-emerald-700 font-bold' : 'text-gray-400');
        }
    }
})();
</script>
@endpush
