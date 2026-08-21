@extends('layouts.app')

@section('title', 'القوالب الذكية')

@section('content')
@php
    $statuses = ['active' => 'نشطة', 'pending' => 'قيد المتابعة', 'overdue' => 'متأخرة', 'closed' => 'مغلقة', 'won' => 'مكسوبة', 'lost' => 'مخسورة', 'adjudicated' => 'مفصولة', 'fees_pending' => 'أتعاب معلقة'];
    $priorities = ['low' => 'منخفضة', 'medium' => 'متوسطة', 'high' => 'عالية', 'urgent' => 'عاجلة'];
    $templatesJs = $templates->keyBy('id')->map(fn ($t) => [
        'name' => $t->name, 'description' => $t->description, 'default_status' => $t->default_status,
        'items' => $t->items ?? [], 'checklist' => $t->checklist ?? [],
        'folders' => $t->folders ?? [], 'reminders' => $t->reminders ?? [],
    ]);
@endphp

<div class="space-y-6" dir="rtl" x-data='templateBuilder(@json($templatesJs))'>

    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gold-dark">📋 القوالب الذكية</h1>
            <p class="text-sm text-gray-500 mt-1">قالب واحد يجهّز القضية كاملة: مهام، قائمة تحقق، مجلدات، وتذكيرات — بضغطة واحدة</p>
        </div>
        <button type="button" @click="open()" class="bg-primary hover:bg-primary-dark text-white px-5 py-2.5 rounded-lg font-semibold transition text-sm">+ قالب جديد</button>
    </div>

    <div class="bg-white rounded-xl border border-gold/15 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-right">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="px-4 py-3 text-gold-dark font-bold text-xs">القالب</th>
                        <th class="px-4 py-3 text-gold-dark font-bold text-xs">المحتوى</th>
                        <th class="px-4 py-3 text-gold-dark font-bold text-xs">الاستخدام</th>
                        <th class="px-4 py-3 text-gold-dark font-bold text-xs">آخر تعديل</th>
                        <th class="px-4 py-3 text-gold-dark font-bold text-xs">الحالة</th>
                        <th class="px-4 py-3 text-gold-dark font-bold text-xs">إجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($templates as $t)
                        @php $s = $t->summary(); @endphp
                        <tr class="hover:bg-gray-50 transition {{ $t->is_active ? '' : 'opacity-60' }}">
                            <td class="px-4 py-3">
                                <p class="font-bold text-gray-900 text-sm">{{ $t->name }}</p>
                                @if($t->description)<p class="text-[11px] text-gray-400">{{ $t->description }}</p>@endif
                                @if($t->creator)<p class="text-[10px] text-gray-300 mt-0.5">أنشأه {{ $t->creator->name }}</p>@endif
                            </td>
                            <td class="px-4 py-3 text-[11px] text-gray-500 whitespace-nowrap">
                                ✅ {{ $s['tasks'] }} مهام • ☑️ {{ $s['checklist'] }} بنود<br>
                                🗂 {{ $s['folders'] }} مجلدات • ⏰ {{ $s['reminders'] }} تذكيرات
                            </td>
                            <td class="px-4 py-3 text-xs font-bold text-gray-700">{{ $t->usage_count }} قضية</td>
                            <td class="px-4 py-3 text-xs text-gray-400 whitespace-nowrap">{{ $t->updated_at?->format('Y-m-d') }}</td>
                            <td class="px-4 py-3">
                                <span class="text-[11px] font-bold px-2 py-0.5 rounded-full {{ $t->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $t->is_active ? 'مفعّل' : 'معطّل' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <button type="button" @click="open({{ $t->id }})" class="text-[11px] font-bold px-2 py-1 rounded-lg border border-gold/25 text-gold-dark hover:bg-gold/10 transition">تعديل</button>
                                    <form method="POST" action="{{ route('case-templates.duplicate', $t) }}">
                                        @csrf
                                        <button class="text-[11px] font-bold px-2 py-1 rounded-lg border border-blue-200 text-blue-700 hover:bg-blue-50 transition">نسخ</button>
                                    </form>
                                    <form method="POST" action="{{ route('case-templates.toggle', $t) }}">
                                        @csrf
                                        <button class="text-[11px] font-bold px-2 py-1 rounded-lg border transition {{ $t->is_active ? 'border-amber-200 text-amber-700 hover:bg-amber-50' : 'border-green-300 text-green-700 hover:bg-green-50' }}">
                                            {{ $t->is_active ? 'تعطيل' : 'تفعيل' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('case-templates.destroy', $t) }}"
                                          onsubmit="return confirm('{{ $t->usage_count > 0 ? 'هذا القالب مستخدم — سيُعطَّل بدلاً من حذفه. متابعة؟' : 'حذف القالب نهائياً؟' }}')">
                                        @csrf @method('DELETE')
                                        <button class="text-[11px] font-bold px-2 py-1 rounded-lg border border-red-200 text-red-700 hover:bg-red-50 transition">حذف</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-gray-400">
                                <p class="text-3xl mb-2">📋</p>
                                <p class="text-sm font-bold text-gray-500 mb-1">لا توجد قوالب بعد</p>
                                <p class="text-xs">أنشئ قالبك الأول — مثلاً «قضية تجارية» بمهامها وقائمة تحققها ومجلداتها.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ===== Template Builder (Modal) ===== --}}
    <div x-show="openModal" x-cloak class="fixed inset-0 z-[90] flex items-start justify-center p-4 overflow-y-auto" dir="rtl">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="openModal = false"></div>
        <form :action="editingId ? '{{ url('case-templates') }}/' + editingId : '{{ route('case-templates.store') }}'" method="POST"
              class="relative w-full max-w-2xl bg-white rounded-2xl border border-gold/25 shadow-2xl my-8"
              x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-3" x-transition:enter-end="opacity-100 translate-y-0">
            @csrf
            <template x-if="editingId"><input type="hidden" name="_method" value="PUT"></template>

            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-bold text-gold-dark" x-text="editingId ? 'تعديل القالب' : 'قالب ذكي جديد'"></h3>
                <button type="button" @click="openModal = false" class="text-gray-400 hover:text-gray-700 text-xl leading-none">✕</button>
            </div>

            <div class="p-6 space-y-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1.5">اسم القالب *</label>
                        <input type="text" name="name" x-model="tpl.name" required maxlength="100" placeholder="مثال: قضية تجارية"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-gold-dark">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1.5">الحالة الافتراضية للقضية</label>
                        <select name="default_status" x-model="tpl.default_status" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm bg-white">
                            <option value="">— بدون تغيير —</option>
                            @foreach($statuses as $k => $v)
                                <option value="{{ $k }}">{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-gray-600 mb-1.5">الوصف</label>
                        <input type="text" name="description" x-model="tpl.description" maxlength="190"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm bg-white">
                    </div>
                </div>

                {{-- المهام --}}
                <div class="rounded-xl border border-gray-200 p-4">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-bold text-gray-700">✅ المهام (تُسند لمحامي القضية بمواعيد محسوبة)</p>
                        <button type="button" @click="tpl.items.push({title:'', days_offset:1, priority:'medium'})" class="text-[11px] font-bold text-gold-dark border border-gold/25 px-2 py-1 rounded-lg hover:bg-gold/10 transition">+ مهمة</button>
                    </div>
                    <template x-for="(it, i) in tpl.items" :key="i">
                        <div class="flex items-center gap-2 mb-2">
                            <input type="text" :name="'items['+i+'][title]'" x-model="it.title" maxlength="190" placeholder="عنوان المهمة" class="flex-1 border border-gray-300 rounded-lg px-2 py-2 text-xs bg-white">
                            <input type="number" min="0" max="365" :name="'items['+i+'][days_offset]'" x-model="it.days_offset" title="بعد كم يوم" class="w-16 border border-gray-300 rounded-lg px-2 py-2 text-xs bg-white">
                            <select :name="'items['+i+'][priority]'" x-model="it.priority" class="w-24 border border-gray-300 rounded-lg px-1.5 py-2 text-xs bg-white">
                                @foreach($priorities as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                            </select>
                            <button type="button" @click="tpl.items.splice(i,1)" class="text-red-400 hover:text-red-600 text-sm px-1">✕</button>
                        </div>
                    </template>
                    <p x-show="!tpl.items.length" class="text-[11px] text-gray-400">لا مهام — أضف مهمة أو اترك القسم فارغاً.</p>
                </div>

                {{-- قائمة التحقق --}}
                <div class="rounded-xl border border-gray-200 p-4">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-bold text-gray-700">☑️ قائمة التحقق (بنود تُعلَّم داخل صفحة القضية)</p>
                        <button type="button" @click="tpl.checklist.push({title:''})" class="text-[11px] font-bold text-gold-dark border border-gold/25 px-2 py-1 rounded-lg hover:bg-gold/10 transition">+ بند</button>
                    </div>
                    <template x-for="(c, i) in tpl.checklist" :key="i">
                        <div class="flex items-center gap-2 mb-2">
                            <input type="text" :name="'checklist['+i+'][title]'" x-model="c.title" maxlength="190" placeholder="مثال: التحقق من الوكالة" class="flex-1 border border-gray-300 rounded-lg px-2 py-2 text-xs bg-white">
                            <button type="button" @click="tpl.checklist.splice(i,1)" class="text-red-400 hover:text-red-600 text-sm px-1">✕</button>
                        </div>
                    </template>
                </div>

                {{-- المجلدات --}}
                <div class="rounded-xl border border-gray-200 p-4">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-bold text-gray-700">🗂 مجلدات المستندات (تنظيم ملفات القضية)</p>
                        <button type="button" @click="tpl.folders.push({name:''})" class="text-[11px] font-bold text-gold-dark border border-gold/25 px-2 py-1 rounded-lg hover:bg-gold/10 transition">+ مجلد</button>
                    </div>
                    <template x-for="(f, i) in tpl.folders" :key="i">
                        <div class="flex items-center gap-2 mb-2">
                            <input type="text" :name="'folders['+i+'][name]'" x-model="f.name" maxlength="100" placeholder="مثال: المستندات، المحكمة، المراسلات" class="flex-1 border border-gray-300 rounded-lg px-2 py-2 text-xs bg-white">
                            <button type="button" @click="tpl.folders.splice(i,1)" class="text-red-400 hover:text-red-600 text-sm px-1">✕</button>
                        </div>
                    </template>
                </div>

                {{-- التذكيرات --}}
                <div class="rounded-xl border border-gray-200 p-4">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-bold text-gray-700">⏰ التذكيرات (إشعار داخلي في موعده)</p>
                        <button type="button" @click="tpl.reminders.push({title:'', days_offset:7, target:'lawyer'})" class="text-[11px] font-bold text-gold-dark border border-gold/25 px-2 py-1 rounded-lg hover:bg-gold/10 transition">+ تذكير</button>
                    </div>
                    <template x-for="(r, i) in tpl.reminders" :key="i">
                        <div class="flex items-center gap-2 mb-2">
                            <input type="text" :name="'reminders['+i+'][title]'" x-model="r.title" maxlength="190" placeholder="نص التذكير" class="flex-1 border border-gray-300 rounded-lg px-2 py-2 text-xs bg-white">
                            <input type="number" min="0" max="365" :name="'reminders['+i+'][days_offset]'" x-model="r.days_offset" title="بعد كم يوم" class="w-16 border border-gray-300 rounded-lg px-2 py-2 text-xs bg-white">
                            <select :name="'reminders['+i+'][target]'" x-model="r.target" class="w-28 border border-gray-300 rounded-lg px-1.5 py-2 text-xs bg-white">
                                <option value="lawyer">المحامي</option>
                                <option value="manager">المدير</option>
                                <option value="both">كلاهما</option>
                            </select>
                            <button type="button" @click="tpl.reminders.splice(i,1)" class="text-red-400 hover:text-red-600 text-sm px-1">✕</button>
                        </div>
                    </template>
                </div>
            </div>

            <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-end gap-3">
                <button type="button" @click="openModal = false" class="text-sm text-gray-500 hover:text-gray-800 px-4 py-2">إلغاء</button>
                <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-lg font-bold text-sm transition"
                        x-text="editingId ? 'حفظ التعديلات' : 'إنشاء القالب'"></button>
            </div>
        </form>
    </div>
</div>

<script nonce="{{ $cspNonce ?? '' }}">
document.addEventListener('alpine:init', () => {
    Alpine.data('templateBuilder', (existing) => ({
        existing,
        openModal: false,
        editingId: null,
        tpl: { name: '', description: '', default_status: '', items: [], checklist: [], folders: [], reminders: [] },

        open(id = null) {
            this.editingId = id;
            if (id && this.existing[id]) {
                this.tpl = JSON.parse(JSON.stringify(Object.assign(
                    { name: '', description: '', default_status: '', items: [], checklist: [], folders: [], reminders: [] },
                    this.existing[id]
                )));
                this.tpl.default_status = this.tpl.default_status || '';
                this.tpl.description = this.tpl.description || '';
            } else {
                this.tpl = {
                    name: '', description: '', default_status: '',
                    items: [{ title: '', days_offset: 1, priority: 'medium' }],
                    checklist: [{ title: '' }],
                    folders: [{ name: 'المستندات' }, { name: 'المحكمة' }, { name: 'المراسلات' }],
                    reminders: [],
                };
            }
            this.openModal = true;
        },
    }));
});
</script>
@endsection
