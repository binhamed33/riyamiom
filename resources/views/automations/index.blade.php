@extends('layouts.app')

@section('title', 'مركز الأتمتة')

@section('content')
@php
    $registry = [
        'triggers' => collect($triggers)->map(fn ($t, $k) => ['key' => $k, 'label' => $t['label'], 'subject' => $t['subject'], 'description' => $t['description']])->values(),
        'fields' => collect($conditionFields)->map(fn ($f, $k) => ['key' => $k, 'label' => $f['label'], 'type' => $f['type'], 'subjects' => $f['subjects'], 'options' => $f['options'] ?? null])->values(),
        'operators' => collect($operators)->map(fn ($l, $k) => ['key' => $k, 'label' => $l])->values(),
        'actions' => collect($actionTypes)->map(fn ($a, $k) => ['key' => $k, 'label' => $a['label']])->values(),
        'users' => $teamUsers->map(fn ($u) => ['id' => (string) $u->id, 'name' => $u->name])->values(),
        'statuses' => ['active' => 'نشطة', 'pending' => 'قيد المتابعة', 'overdue' => 'متأخرة', 'closed' => 'مغلقة', 'won' => 'مكسوبة', 'lost' => 'مخسورة', 'adjudicated' => 'مفصولة', 'fees_pending' => 'أتعاب معلقة'],
        'priorities' => ['low' => 'منخفضة', 'medium' => 'متوسطة', 'high' => 'عالية', 'urgent' => 'عاجلة'],
    ];
    $triggerLabels = collect($triggers)->map(fn ($t) => $t['label']);
    $rulesJs = $automations->keyBy('id')->map(fn ($a) => [
        'id' => $a->id,
        'name' => $a->name,
        'trigger' => $a->trigger,
        'conditions' => $a->conditions ?? [],
        'actions' => $a->actions ?? [],
    ]);
@endphp

<div class="space-y-6" dir="rtl"
     x-data='automationCenter(@json($registry), @json($rulesJs))'>

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gold-dark">⚙️ مركز الأتمتة</h1>
            <p class="text-sm text-gray-500 mt-1">قواعد تجعل مُداوَلة تعمل نيابة عنك: متى → إذا → نفّذ</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('automations.runs') }}" class="bg-white hover:bg-gray-100 text-gray-600 border border-gold/15 px-4 py-2.5 rounded-lg font-medium transition text-sm">
                📜 سجل التنفيذ
            </a>
            <button type="button" @click="aiOpen = !aiOpen" class="bg-white border border-gold/30 text-gold-dark hover:bg-gold/5 px-4 py-2.5 rounded-lg font-semibold transition text-sm">✨ توليد بالذكاء</button>
            <button type="button" @click="openBuilder()" class="bg-primary hover:bg-primary-dark text-white px-5 py-2.5 rounded-lg font-semibold transition text-sm">
                + قاعدة جديدة
            </button>
        </div>
    </div>

    {{-- Engine master switch + stats --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl border {{ $engineEnabled ? 'border-green-200' : 'border-amber-300' }} p-4 flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-400 mb-1">محرك الأتمتة</p>
                <p class="font-bold {{ $engineEnabled ? 'text-green-700' : 'text-amber-600' }}">
                    {{ $engineEnabled ? '● يعمل' : '○ متوقف' }}
                </p>
            </div>
            <form method="POST" action="{{ route('automations.engine') }}"
                  data-confirm="{{ $engineEnabled ? 'إيقاف محرك الأتمتة بالكامل؟ القواعد تبقى محفوظة.' : 'تفعيل محرك الأتمتة؟' }}">
                @csrf
                <button type="submit" class="text-xs font-bold px-3 py-1.5 rounded-lg border transition {{ $engineEnabled ? 'text-red-700 border-red-200 hover:bg-red-50' : 'text-green-700 border-green-300 hover:bg-green-50' }}">
                    {{ $engineEnabled ? 'إيقاف' : 'تفعيل' }}
                </button>
            </form>
        </div>

        {{-- تفعيل/تعطيل كل القواعد.
             غير إيقاف المحرّك: ذاك يوقف التشغيل كلّه وحالةُ القواعد
             تحته لا تتغيّر؛ وهذا يغيّر حالة القواعد نفسها. ولا يحذف
             شيئًا في الحالتين. --}}
        <div class="bg-white rounded-xl border border-gold/15 p-4 flex items-center justify-between gap-3">
            <div>
                <p class="text-xs text-gray-400 mb-1">كل القواعد</p>
                <p class="font-bold text-gray-900">{{ $automations->where('is_active', true)->count() }} / {{ $automations->count() }} مفعّلة</p>
            </div>
            <div class="flex gap-2">
                <form method="POST" action="{{ route('automations.bulk') }}"
                      data-confirm="تفعيل كل القواعد؟">
                    @csrf
                    <input type="hidden" name="action" value="enable">
                    <button type="submit" class="text-xs font-bold px-3 py-1.5 rounded-lg border border-green-300 text-green-700 hover:bg-green-50 transition">تفعيل الكل</button>
                </form>
                <form method="POST" action="{{ route('automations.bulk') }}"
                      data-confirm="تعطيل كل القواعد؟ تبقى محفوظة ويمكن إعادتها بضغطة.">
                    @csrf
                    <input type="hidden" name="action" value="disable">
                    <button type="submit" class="text-xs font-bold px-3 py-1.5 rounded-lg border border-amber-300 text-amber-700 hover:bg-amber-50 transition">تعطيل الكل</button>
                </form>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-gold/15 p-4">
            <p class="text-xs text-gray-400 mb-1">اليوم</p>
            <p class="text-2xl font-bold text-gold-dark">{{ $todayRuns }} <span class="text-xs font-semibold text-gray-400">نجاح</span>
                @if(($stats['today_failed'] ?? 0) > 0)<span class="text-sm font-bold text-red-600">+ {{ $stats['today_failed'] }} فشل</span>@endif
            </p>
            @if($stats['most_used'] ?? null)
                <p class="text-[11px] text-gray-400 mt-1 truncate">الأكثر عملاً: {{ $stats['most_used'] }}</p>
            @endif
        </div>
        <div class="bg-white rounded-xl border border-gold/15 p-4">
            <p class="text-xs text-gray-400 mb-1">إخفاقات آخر 7 أيام</p>
            <p class="text-2xl font-bold {{ $failedRecently ? 'text-red-600' : 'text-gray-700' }}">{{ $failedRecently }}</p>
            @if($failedRecently)
                <a href="{{ route('automations.runs', ['status' => 'failed']) }}" class="text-xs text-red-600 underline">راجع الأخطاء</a>
            @endif
        </div>
    </div>

    {{-- §11: صِف القاعدة بجملة — المولد يملأ المحرِّر وأنت تراجع وتحفظ --}}
    <div x-show="aiOpen" x-cloak class="bg-white rounded-xl border border-gold/25 p-4">
        <p class="text-sm font-bold text-gray-900 mb-1">صِف ما تريد أتمتته بجملة واحدة</p>
        <p class="text-xs text-gray-400 mb-3">مثال: «إذا كانت الجلسة بعد ثلاثة أيام أنشئ مهمة للمحامي المسؤول». المولد يجهّز مسودة تراجعها في المحرِّر قبل حفظها — لا يُحفظ ولا يُفعَّل شيء تلقائياً.</p>
        <div class="flex flex-col sm:flex-row gap-2">
            <input type="text" x-model="aiPrompt" @keydown.enter.prevent="aiGenerate()" maxlength="500"
                   class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:border-gold" placeholder="أريد النظام أن…">
            <button type="button" @click="aiGenerate()" :disabled="aiBusy"
                    class="text-sm font-bold px-5 py-2.5 rounded-lg bg-primary text-white hover:bg-primary-dark transition disabled:opacity-50 md-touch"
                    x-text="aiBusy ? 'يولّد…' : 'توليد المسودة'"></button>
        </div>
        <p x-show="aiError" x-text="aiError" class="text-xs text-red-600 mt-2"></p>
    </div>

    {{-- §12: اقتراحات من نمط استخدام المكتب — لا يُفعَّل شيء تلقائياً --}}
    @if(!empty($suggestions))
        <div class="space-y-3">
            @foreach($suggestions as $sug)
                <div class="bg-white rounded-xl border border-gold/25 p-4 flex flex-col sm:flex-row sm:items-center gap-3">
                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-gray-900 text-sm">💡 {{ $sug['title'] }}</p>
                        <p class="text-xs text-gray-500 mt-1 leading-relaxed">{{ $sug['reason'] }}</p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <form method="POST" action="{{ route('automations.suggestions.accept') }}">
                            @csrf
                            <input type="hidden" name="key" value="{{ $sug['key'] }}">
                            <button class="text-xs font-bold px-3.5 py-2 rounded-lg bg-primary text-white hover:bg-primary-dark transition md-touch">تفعيل الاقتراح</button>
                        </form>
                        <form method="POST" action="{{ route('automations.suggestions.dismiss') }}">
                            @csrf
                            <input type="hidden" name="key" value="{{ $sug['key'] }}">
                            <button class="text-xs font-bold px-3.5 py-2 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 transition md-touch">تجاهل</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Rules list --}}
    <div class="bg-white rounded-xl border border-gold/15 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 flex flex-wrap items-center gap-3">
            <h2 class="font-bold text-gold-dark text-sm">القواعد ({{ $automations->count() }})</h2>

            {{-- §29: بحث وتصفية من الخادم --}}
            <form method="GET" action="{{ route('automations.index') }}" class="flex flex-wrap items-center gap-2 ms-auto">
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="بحث باسم القاعدة…"
                       class="text-xs border border-gray-200 rounded-lg px-3 py-1.5 w-40 focus:outline-none focus:border-gold">
                <select name="state" class="text-xs border border-gray-200 rounded-lg px-2 py-1.5">
                    <option value="">الكل</option>
                    <option value="active" @selected($filters['state'] === 'active')>المفعّلة</option>
                    <option value="disabled" @selected($filters['state'] === 'disabled')>المعطّلة</option>
                </select>
                <select name="sort" class="text-xs border border-gray-200 rounded-lg px-2 py-1.5">
                    <option value="recent" @selected($filters['sort'] === 'recent')>الأحدث</option>
                    <option value="most_used" @selected($filters['sort'] === 'most_used')>الأكثر استخداماً</option>
                    <option value="name" @selected($filters['sort'] === 'name')>الاسم</option>
                </select>
                <button class="text-xs font-bold px-3 py-1.5 rounded-lg border border-gold/25 text-gold-dark hover:bg-gold/10 transition">تصفية</button>
            </form>

            <form method="POST" action="{{ route('automations.seed') }}"
                  data-confirm="إضافة قواعد مُداوَلة الجاهزة؟ الموجود منها لا يُمسّ — يُضاف الناقص فقط.">
                @csrf
                <button class="text-xs font-bold text-gold-dark border border-gold/25 px-3 py-1.5 rounded-lg hover:bg-gold/10 transition">✨ القواعد الجاهزة</button>
            </form>
        </div>

        @forelse($automations as $rule)
            <div class="px-5 py-4 border-b border-gray-50 last:border-0 flex flex-col md:flex-row md:items-center gap-3">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="w-2 h-2 rounded-full {{ $rule->is_active ? 'bg-green-500' : 'bg-gray-300' }}"></span>
                        <span class="font-bold text-gray-900">{{ $rule->name }}</span>
                        <span class="text-[11px] px-2 py-0.5 rounded-full bg-gold/10 text-gold-dark border border-gold/15">{{ $triggerLabels[$rule->trigger] ?? $rule->trigger }}</span>
                        @unless($rule->is_active)
                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">معطّلة</span>
                        @endunless
                    </div>
                    <p class="text-xs text-gray-400 mt-1">
                        {{ count($rule->conditions ?? []) }} شرط • {{ count($rule->actions ?? []) }} إجراء
                        • نُفذت {{ $rule->success_runs_count }} مرة
                        @if($rule->last_run_at) • آخر تشغيل {{ $rule->last_run_at->diffForHumans() }} @endif
                        @if($rule->creator) • أنشأها {{ $rule->creator->name }} @endif
                    </p>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <form method="POST" action="{{ route('automations.test', $rule) }}">
                        @csrf
                        <button class="text-[11px] font-bold px-2.5 py-1.5 rounded-lg border border-blue-200 text-blue-700 hover:bg-blue-50 transition" title="كم عنصراً سيطابق الآن؟ لا يُنفَّذ شيء">🧪 اختبار</button>
                    </form>
                    <button type="button" @click="openBuilder({{ $rule->id }})" class="text-[11px] font-bold px-2.5 py-1.5 rounded-lg border border-gold/25 text-gold-dark hover:bg-gold/10 transition">تعديل</button>
                    <form method="POST" action="{{ route('automations.duplicate', $rule) }}">
                        @csrf
                        <button class="text-[11px] font-bold px-2.5 py-1.5 rounded-lg border border-blue-200 text-blue-700 hover:bg-blue-50 transition">نسخ</button>
                    </form>
                    <a href="{{ route('automations.versions', $rule) }}" class="text-[11px] font-bold px-2.5 py-1.5 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 transition">النسخ السابقة</a>
                    <form method="POST" action="{{ route('automations.toggle', $rule) }}">
                        @csrf
                        <button class="text-[11px] font-bold px-2.5 py-1.5 rounded-lg border transition {{ $rule->is_active ? 'border-amber-200 text-amber-700 hover:bg-amber-50' : 'border-green-300 text-green-700 hover:bg-green-50' }}">
                            {{ $rule->is_active ? 'تعطيل' : 'تفعيل' }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('automations.destroy', $rule) }}" data-confirm="حذف القاعدة «{{ $rule->name }}» نهائياً؟ سجل تنفيذها التاريخي يبقى محفوظاً.">
                        @csrf @method('DELETE')
                        <button class="text-[11px] font-bold px-2.5 py-1.5 rounded-lg border border-red-200 text-red-700 hover:bg-red-50 transition">حذف</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="px-5 py-12 text-center text-gray-400">
                <p class="text-3xl mb-2">⚙️</p>
                <p class="text-sm font-bold text-gray-500 mb-1">لا توجد قواعد بعد</p>
                <p class="text-xs">ابدأ بالقواعد الجاهزة أو أنشئ قاعدتك الأولى — بدون أي كود.</p>
            </div>
        @endforelse
    </div>

    {{-- ===== Visual Rule Builder (Modal) ===== --}}
    <div x-show="builderOpen" x-cloak class="fixed inset-0 z-[90] flex items-start justify-center p-4 overflow-y-auto" dir="rtl">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="builderOpen = false"></div>
        <form :action="editingId ? '{{ url('automations') }}/' + editingId : '{{ route('automations.store') }}'" method="POST"
              class="relative w-full max-w-2xl bg-white rounded-2xl border border-gold/25 shadow-2xl my-8"
              x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-3" x-transition:enter-end="opacity-100 translate-y-0">
            @csrf
            <template x-if="editingId"><input type="hidden" name="_method" value="PUT"></template>

            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-bold text-gold-dark" x-text="editingId ? 'تعديل القاعدة' : 'قاعدة أتمتة جديدة'"></h3>
                <button type="button" @click="builderOpen = false" class="text-gray-400 hover:text-gray-700 text-xl leading-none">✕</button>
            </div>

            <div class="p-6 space-y-6">
                {{-- الاسم --}}
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1.5">اسم القاعدة *</label>
                    <input type="text" name="name" x-model="rule.name" required maxlength="190" placeholder="مثال: تحضير الجلسات القادمة"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-gold-dark bg-white">
                </div>

                {{-- متى --}}
                <div class="rounded-xl border border-gold/20 p-4 bg-gold/5">
                    <p class="text-xs font-bold text-gold-dark mb-2">١ — متى تعمل؟ (المشغّل)</p>
                    <select name="trigger" x-model="rule.trigger" @change="onTriggerChange()" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-gold-dark">
                        <template x-for="t in registry.triggers" :key="t.key">
                            <option :value="t.key" x-text="t.label"></option>
                        </template>
                    </select>
                    <p class="text-[11px] text-gray-500 mt-1.5" x-text="currentTrigger()?.description"></p>
                </div>

                {{-- إذا --}}
                <div class="rounded-xl border border-blue-100 p-4 bg-blue-50/40">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-bold text-blue-800">٢ — إذا… (شروط اختيارية، كلها يجب أن تتحقق)</p>
                        <button type="button" @click="rule.conditions.push({field:'',operator:'equals',value:''})"
                                class="text-[11px] font-bold text-blue-700 border border-blue-200 px-2 py-1 rounded-lg hover:bg-blue-100 transition">+ شرط</button>
                    </div>
                    <template x-if="rule.conditions.length === 0">
                        <p class="text-[11px] text-gray-400">بدون شروط — تنطبق القاعدة على كل العناصر التي يجدها المشغّل.</p>
                    </template>
                    <template x-for="(c, i) in rule.conditions" :key="i">
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <select :name="'conditions['+i+'][field]'" x-model="c.field"
                                    class="flex-1 min-w-[130px] border border-gray-300 rounded-lg px-2 py-2 text-xs bg-white">
                                <option value="">— اختر الحقل —</option>
                                <template x-for="f in fieldsForTrigger()" :key="f.key">
                                    <option :value="f.key" x-text="f.label"></option>
                                </template>
                            </select>
                            <select :name="'conditions['+i+'][operator]'" x-model="c.operator"
                                    class="w-32 border border-gray-300 rounded-lg px-2 py-2 text-xs bg-white">
                                <template x-for="op in registry.operators" :key="op.key">
                                    <option :value="op.key" x-text="op.label"></option>
                                </template>
                            </select>
                            {{-- القيمة: select للحقول ذات الخيارات، رقم للأرقام، مستخدم، أو نص --}}
                            <template x-if="fieldDef(c.field)?.options">
                                <select :name="'conditions['+i+'][value]'" x-model="c.value" class="flex-1 min-w-[120px] border border-gray-300 rounded-lg px-2 py-2 text-xs bg-white">
                                    <option value="">—</option>
                                    <template x-for="[k, v] in Object.entries(fieldDef(c.field).options)" :key="k">
                                        <option :value="k" x-text="v"></option>
                                    </template>
                                </select>
                            </template>
                            <template x-if="fieldDef(c.field)?.type === 'user'">
                                <select :name="'conditions['+i+'][value]'" x-model="c.value" class="flex-1 min-w-[120px] border border-gray-300 rounded-lg px-2 py-2 text-xs bg-white">
                                    <option value="">—</option>
                                    <template x-for="u in registry.users" :key="u.id">
                                        <option :value="u.id" x-text="u.name"></option>
                                    </template>
                                </select>
                            </template>
                            <template x-if="fieldDef(c.field)?.type === 'number'">
                                <input type="number" min="0" max="365" :name="'conditions['+i+'][value]'" x-model="c.value"
                                       class="w-24 border border-gray-300 rounded-lg px-2 py-2 text-xs bg-white">
                            </template>
                            <template x-if="!fieldDef(c.field) || fieldDef(c.field)?.type === 'text'">
                                <input type="text" :name="'conditions['+i+'][value]'" x-model="c.value" placeholder="القيمة"
                                       class="flex-1 min-w-[100px] border border-gray-300 rounded-lg px-2 py-2 text-xs bg-white">
                            </template>
                            <button type="button" @click="rule.conditions.splice(i, 1)" class="text-red-400 hover:text-red-600 text-sm px-1">✕</button>
                        </div>
                    </template>
                </div>

                {{-- نفّذ --}}
                <div class="rounded-xl border border-green-100 p-4 bg-green-50/40">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-bold text-green-800">٣ — نفّذ (إجراء واحد على الأقل)</p>
                        <button type="button" @click="rule.actions.push({type:'create_task', title:'', priority:'high', assign:'case_lawyer', target:'manager', message:'', status:'active', due_in_days:1})"
                                class="text-[11px] font-bold text-green-700 border border-green-200 px-2 py-1 rounded-lg hover:bg-green-100 transition">+ إجراء</button>
                    </div>
                    <template x-for="(a, i) in rule.actions" :key="i">
                        <div class="rounded-lg border border-gray-200 bg-white p-3 mb-2">
                            <div class="flex items-center gap-2 mb-2">
                                <select :name="'actions['+i+'][type]'" x-model="a.type" class="flex-1 border border-gray-300 rounded-lg px-2 py-2 text-xs bg-white font-bold">
                                    <template x-for="t in registry.actions" :key="t.key">
                                        <option :value="t.key" x-text="t.label"></option>
                                    </template>
                                </select>
                                <button type="button" @click="rule.actions.splice(i, 1)" x-show="rule.actions.length > 1" class="text-red-400 hover:text-red-600 text-sm px-1">✕</button>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <template x-if="a.type === 'create_task' || a.type === 'add_timeline_event' || a.type === 'create_reminder'">
                                    <input type="text" :name="'actions['+i+'][title]'" x-model="a.title" maxlength="190"
                                           :placeholder="a.type === 'create_task' ? 'عنوان المهمة — المتغيرات: {case} {client} {date}' : 'النص'"
                                           class="sm:col-span-2 border border-gray-300 rounded-lg px-2 py-2 text-xs bg-white">
                                </template>
                                <template x-if="a.type === 'notify'">
                                    <input type="text" :name="'actions['+i+'][message]'" x-model="a.message" maxlength="500"
                                           placeholder="نص الإشعار — المتغيرات: {case} {client} {date}"
                                           class="sm:col-span-2 border border-gray-300 rounded-lg px-2 py-2 text-xs bg-white">
                                </template>
                                <template x-if="a.type === 'create_task'">
                                    <select :name="'actions['+i+'][priority]'" x-model="a.priority" class="border border-gray-300 rounded-lg px-2 py-2 text-xs bg-white">
                                        <template x-for="[k, v] in Object.entries(registry.priorities)" :key="k">
                                            <option :value="k" x-text="'الأولوية: ' + v"></option>
                                        </template>
                                    </select>
                                </template>
                                <template x-if="a.type === 'create_task'">
                                    <select :name="'actions['+i+'][assign]'" x-model="a.assign" class="border border-gray-300 rounded-lg px-2 py-2 text-xs bg-white">
                                        <option value="case_lawyer">إسناد إلى: محامي القضية</option>
                                        <option value="manager">إسناد إلى: المدير</option>
                                <option value="task_assignee">إسناد إلى: المسؤول عن المهمة</option>
                                    </select>
                                </template>
                                <template x-if="a.type === 'notify' || a.type === 'create_reminder'">
                                    <select :name="'actions['+i+'][target]'" x-model="a.target" class="border border-gray-300 rounded-lg px-2 py-2 text-xs bg-white">
                                        <option value="case_lawyer">المستهدف: محامي القضية</option>
                                        <option value="manager">المستهدف: المدير</option>
                                        <option value="task_assignee">المستهدف: المسؤول عن المهمة</option>
                                <option value="both">المستهدف: كلاهما</option>
                                    </select>
                                </template>
                                <template x-if="a.type === 'change_case_status'">
                                    <select :name="'actions['+i+'][status]'" x-model="a.status" class="border border-gray-300 rounded-lg px-2 py-2 text-xs bg-white">
                                        <template x-for="[k, v] in Object.entries(registry.statuses)" :key="k">
                                            <option :value="k" x-text="'الحالة الجديدة: ' + v"></option>
                                        </template>
                                    </select>
                                </template>
                                <template x-if="a.type === 'create_task' || a.type === 'create_reminder'">
                                    <div class="flex items-center gap-1.5">
                                        <input type="number" min="0" max="365" :name="'actions['+i+'][due_in_days]'" x-model="a.due_in_days"
                                               class="w-20 border border-gray-300 rounded-lg px-2 py-2 text-xs bg-white">
                                        <span class="text-[11px] text-gray-500" x-text="a.type === 'create_task' ? 'أيام حتى الاستحقاق' : 'أيام حتى التذكير'"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-end gap-3">
                <button type="button" @click="builderOpen = false" class="text-sm text-gray-500 hover:text-gray-800 px-4 py-2">إلغاء</button>
                <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-lg font-bold text-sm transition"
                        x-text="editingId ? 'حفظ التعديلات' : 'إنشاء القاعدة'"></button>
            </div>
        </form>
    </div>
</div>

<script nonce="{{ $cspNonce ?? '' }}">
document.addEventListener('alpine:init', () => {
    Alpine.data('automationCenter', (registry, existing) => ({
        registry,
        existing,
        builderOpen: false,
        editingId: null,
        rule: { name: '', trigger: 'session_approaching', conditions: [], actions: [] },
        aiOpen: false, aiPrompt: '', aiBusy: false, aiError: '',

        // §11: المولد يملأ الاستمارة نفسها — المراجعة والحفظ بيد المدير
        async aiGenerate() {
            if (this.aiBusy || this.aiPrompt.trim().length < 10) { this.aiError = 'اكتب وصفاً أوضح (١٠ أحرف على الأقل).'; return; }
            this.aiBusy = true; this.aiError = '';
            try {
                const r = await fetch('{{ route('automations.ai-draft') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                               'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    credentials: 'same-origin',
                    body: JSON.stringify({ prompt: this.aiPrompt }),
                });
                const d = await r.json();
                if (!d.ok) { this.aiError = d.error || 'تعذّر التوليد — أعد المحاولة.'; return; }
                this.editingId = null;
                this.rule = d.draft;
                this.rule.actions = (this.rule.actions || []).map(a => Object.assign(
                    { title: '', message: '', priority: 'high', assign: 'case_lawyer', target: 'manager', status: 'active', due_in_days: 1 }, a));
                this.aiOpen = false; this.aiPrompt = '';
                this.builderOpen = true;   // معاينة كاملة قبل أي حفظ
            } catch (e) {
                this.aiError = 'تعذّر الاتصال — تحقق من الشبكة وأعد المحاولة.';
            } finally { this.aiBusy = false; }
        },

        openBuilder(id = null) {
            this.editingId = id;
            if (id && this.existing[id]) {
                const src = this.existing[id];
                this.rule = JSON.parse(JSON.stringify({
                    name: src.name,
                    trigger: src.trigger,
                    conditions: src.conditions || [],
                    actions: (src.actions || []).map(a => Object.assign(
                        { title: '', message: '', priority: 'high', assign: 'case_lawyer', target: 'manager', status: 'active', due_in_days: 1 }, a
                    )),
                }));
            } else {
                this.rule = {
                    name: '', trigger: 'session_approaching', conditions: [],
                    actions: [{ type: 'create_task', title: '', priority: 'high', assign: 'case_lawyer', target: 'manager', message: '', status: 'active', due_in_days: 1 }],
                };
            }
            this.builderOpen = true;
        },
        currentTrigger() { return this.registry.triggers.find(t => t.key === this.rule.trigger); },
        fieldsForTrigger() {
            const subject = this.currentTrigger()?.subject;
            return this.registry.fields.filter(f => f.subjects.includes(subject));
        },
        fieldDef(key) { return this.registry.fields.find(f => f.key === key); },
        onTriggerChange() {
            // أزل الشروط التي لا تنطبق على نوع المشغّل الجديد
            const valid = this.fieldsForTrigger().map(f => f.key);
            this.rule.conditions = this.rule.conditions.filter(c => !c.field || valid.includes(c.field));
        },
    }));
});
</script>
@endsection
