@extends('layouts.app')

@section('title', 'قوالب القضايا')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-bold font-heading">قوالب القضايا</h1>
        <p class="text-sm text-gray-500 mt-1">عند إنشاء قضية بقالب، تُنشأ مهامه تلقائياً بمواعيد محسوبة — بيئة القضية تتجهز وحدها.</p>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="font-bold mb-3">قالب جديد</h2>
        <form method="POST" action="{{ route('case-templates.store') }}" class="space-y-3">
            @csrf
            <div class="grid md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium mb-1">اسم القالب *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required maxlength="100"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2" placeholder="قضية تجارية — افتتاح">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">وصف مختصر</label>
                    <input type="text" name="description" value="{{ old('description') }}" maxlength="190"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">مهام القالب * <span class="text-gray-400 font-normal">— سطر لكل مهمة بصيغة: العنوان | بعد كم يوم | الأولوية (low/medium/high/urgent)</span></label>
                <textarea name="items_text" rows="6" required
                          class="w-full rounded-lg border border-gray-300 px-3 py-2 font-mono text-sm" dir="rtl"
                          placeholder="جمع مستندات الموكل | 2 | high&#10;صياغة صحيفة الدعوى | 5 | high&#10;مراجعة المستشار | 7 | medium">{{ old('items_text') }}</textarea>
                @error('items_text')<div class="text-red-600 text-sm mt-1">{{ $message }}</div>@enderror
            </div>
            <button class="bg-gold-dark text-white px-5 py-2 rounded-lg text-sm font-bold hover:opacity-90 transition">حفظ القالب</button>
        </form>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
        @forelse ($templates as $template)
            <div class="p-4 flex items-start justify-between gap-4">
                <div>
                    <div class="font-bold">{{ $template->name }}</div>
                    @if ($template->description)<div class="text-sm text-gray-500">{{ $template->description }}</div>@endif
                    <ul class="mt-2 text-sm text-gray-600 space-y-0.5">
                        @foreach ($template->items as $item)
                            <li>• {{ $item['title'] }} <span class="text-gray-400">(بعد {{ $item['days_offset'] }} يوم — {{ $item['priority'] }})</span></li>
                        @endforeach
                    </ul>
                </div>
                <form method="POST" action="{{ route('case-templates.destroy', $template) }}"
                      onsubmit="return confirm('حذف القالب «{{ $template->name }}»؟ لا يؤثر على المهام المُنشأة سابقاً.');">
                    @csrf @method('DELETE')
                    <button class="text-red-600 text-sm font-bold hover:underline">حذف</button>
                </form>
            </div>
        @empty
            <div class="p-8 text-center text-gray-400 text-sm">لا قوالب بعد — أنشئ أول قالب أعلاه</div>
        @endforelse
    </div>
</div>
@endsection
