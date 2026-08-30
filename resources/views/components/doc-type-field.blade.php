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

     وكانت `datalist` أصليّةً بلا سكربت، فورثت عيبَها: المتصفّح يرسم
     قائمتها بنفسه فيسكب الأنواعَ الثلاثين عموداً يغطّي الشاشة، ولا سبيل
     إلى ضبط ارتفاعها ولا تنسيقها — الخصائص لا أثر لها على قائمةٍ يملكها
     المتصفّح.

     فصارت `select.ts` كبقيّة قوائم النظام: بحثٌ بالكتابة، وارتفاعٌ محدود،
     وتنسيقٌ يتبع الهوية. و`create` باقٍ فالكتابة الحرّة لم تُفقد، والسكربت
     يحمل nonce كما تشترط سياسة الأمان. --}}
<div>
    <label for="{{ $id }}" class="block {{ $compact ? 'text-xs font-medium text-gray-400' : 'text-sm font-medium text-gray-700' }} mb-1">
        {{ $label }}
    </label>

    <select id="{{ $id }}"
            name="{{ $name }}"
            placeholder="اختر أو اكتب — مثل: وكالة، صحيفة دعوى"
            class="ts w-full rounded-lg bg-white border border-gray-300 text-gray-900 px-3 py-2.5 focus:ring-2 focus:ring-gold focus:border-gold @error($name) border-red-500 @enderror">
        <option value=""></option>
        {{-- القيمة المحفوظة قد تكون نوعاً حُذف من القائمة بعد حفظه، فتُدرج
             صراحةً وإلا سقطت عن المستند عند أوّل تعديل. --}}
        @if ($value && !collect($types)->contains($value))
            <option value="{{ $value }}" selected>{{ $value }}</option>
        @endif
        @foreach ($types as $type)
            <option value="{{ $type }}" @selected($value === $type)>{{ $type }}</option>
        @endforeach
    </select>

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
