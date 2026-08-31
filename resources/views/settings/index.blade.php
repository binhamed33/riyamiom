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
                        مفتاح ونموذج خاصان بمكتبك وحده — لا يشاركه أي مكتب آخر.
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
