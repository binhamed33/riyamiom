@props([
    'name' => 'doc_type',
    'id' => null,
    'value' => null,
    'types' => null,
    'label' => 'نوع المستند',
    'compact' => false,
])

@php
    $id = $id ?: $name;
    $types = $types ?? \App\Models\DocumentType::active()->pluck('name');
    $value = old($name, $value);
    $listId = $id . '-options';
    $canManage = auth()->check() && in_array(auth()->user()->role, ['developer', 'admin'], true);
@endphp

{{-- خانة نوع المستند: تُختار أو تُكتب.

     كانت قائمةً مغلقة: إمّا نوعٌ منها أو لا نوع. فالمحامي الذي يرفع
     «لائحة تظلّم» ولم يُدرجها أحدٌ من قبل يترك الخانة فارغة ويمضي —
     ويضيع التصنيف على من يبحث بعده.

     datalist لا سكربت: القائمة تظهر عند النقر، والكتابة حرّة، وما
     يُكتب جديداً يُحفظ في قائمة المكتب فيراه من بعده. ولا شيء منها
     يحتاج تنفيذ جافاسكربت — فتعمل تحت سياسة الأمان كما هي. --}}
<div>
    <label for="{{ $id }}" class="block {{ $compact ? 'text-xs font-medium text-gray-400' : 'text-sm font-medium text-gray-700' }} mb-1">
        {{ $label }}
    </label>

    <input type="text"
           id="{{ $id }}"
           name="{{ $name }}"
           value="{{ $value }}"
           list="{{ $listId }}"
           maxlength="80"
           autocomplete="off"
           placeholder="اختر أو اكتب — مثل: وكالة، صحيفة دعوى"
           class="w-full rounded-lg bg-white border border-gray-300 text-gray-900 px-3 py-2.5 focus:ring-2 focus:ring-gold focus:border-gold @error($name) border-red-500 @enderror">

    <datalist id="{{ $listId }}">
        @foreach ($types as $type)
            <option value="{{ $type }}"></option>
        @endforeach
    </datalist>

    @error($name)
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror

    <p class="mt-1 text-xs text-gray-500">
        اتركه فارغاً ليستنتجه النظام من اسم الملف.
        @if ($canManage)
            <a href="{{ route('document-types.index') }}" class="text-gold-dark font-semibold hover:underline">إدارة الأنواع</a>
        @endif
    </p>
</div>
