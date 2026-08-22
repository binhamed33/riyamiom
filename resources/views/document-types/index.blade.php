@extends('layouts.app')
@section('title', 'أنواع المستندات')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">أنواع المستندات</h1>
        <p class="text-sm text-gray-500 mt-1">
            القائمة التي تظهر عند رفع مستند. أضف ما يخصّ مكتبك، وعطّل ما لا تستعمله —
            المستندات التي تحمل نوعاً معطَّلاً تبقى كما هي.
        </p>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm font-semibold">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm font-semibold">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('document-types.store') }}"
          class="bg-white rounded-xl border border-gray-200 p-4 mb-5 flex flex-wrap gap-3 items-end">
        @csrf
        <label class="flex-1 min-w-52">
            <span class="block text-xs font-semibold text-gray-600 mb-1">إضافة نوع جديد</span>
            <input type="text" name="name" maxlength="80" required placeholder="مثال: تقرير خبرة"
                   class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-gold-dark">
        </label>
        <button class="bg-primary hover:bg-primary-dark text-white px-5 py-2 rounded-lg font-semibold text-sm">إضافة</button>
        @error('name')<p class="w-full text-sm text-red-700">{{ $message }}</p>@enderror
    </form>

    @if ($untyped > 0)
        <div class="mb-4 rounded-lg bg-gray-50 border border-gray-200 px-4 py-3 text-sm text-gray-600">
            <span class="font-semibold">{{ $untyped }}</span> مستنداً بلا نوع — يظهر باسم «غير محدد»، ويمكن تحديد نوعه عند التعديل.
        </div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
        @foreach ($types as $type)
            @php $used = $usage[$type->name] ?? 0; @endphp
            <div class="px-4 py-3 flex flex-wrap items-center gap-3">
                <form method="POST" action="{{ route('document-types.update', $type) }}" class="flex-1 min-w-52 flex gap-2">
                    @csrf @method('PUT')
                    <input type="text" name="name" value="{{ $type->name }}" maxlength="80" required
                           class="flex-1 rounded-lg border border-gray-200 px-3 py-1.5 text-sm {{ $type->is_active ? '' : 'text-gray-400' }}">
                    <button class="text-xs font-semibold text-gray-500 hover:text-gray-900 px-2">حفظ</button>
                </form>

                <span class="text-xs text-gray-400 whitespace-nowrap">
                    @if ($used > 0){{ $used }} مستند @else غير مستعمل @endif
                </span>

                @unless ($type->is_active)
                    <span class="text-[11px] font-bold text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full">معطَّل</span>
                @endunless

                <form method="POST" action="{{ route('document-types.toggle', $type) }}">
                    @csrf
                    <button class="text-xs font-semibold {{ $type->is_active ? 'text-gray-500' : 'text-green-700' }} hover:underline">
                        {{ $type->is_active ? 'تعطيل' : 'تفعيل' }}
                    </button>
                </form>

                @if (!$type->is_builtin && $used === 0)
                    <form method="POST" action="{{ route('document-types.destroy', $type) }}"
                          data-confirm="حذف النوع «{{ $type->name }}»؟">
                        @csrf @method('DELETE')
                        <button class="text-xs font-semibold text-red-700 hover:underline">حذف</button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endsection
