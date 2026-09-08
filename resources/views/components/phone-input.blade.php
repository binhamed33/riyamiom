@props([
    'name' => 'phone',
    'id' => null,
    'label' => null,
    'value' => null,
    'required' => false,
    'note' => null,
    'theme' => 'light',
])

@php
    $id = $id ?: str_replace(['[', ']', '.'], ['-', '', '-'], $name);
    $countryName = $name . \App\Http\Middleware\NormalizePhoneNumbers::COUNTRY_SUFFIX;

    $raw = old($name, $value);

    // الدولةُ المعروضة: ما اختاره المستخدم قبل الرفض إن كان، وإلا ما
    // يقوله الرقمُ المحفوظ نفسُه، وإلا عُمان
    $chosen = strtoupper((string) old($countryName));
    $chosen = ($chosen !== '' && \App\Support\Phone::dialCode($chosen) !== null) ? $chosen : null;
    $iso = $chosen ?: \App\Support\Phone::region($raw);

    // الحقلُ يعرض الجزءَ المحلّيَّ وحده — المفتاحُ في المنتقي بجانبه.
    // ورقمٌ محفوظٌ بلا دولةٍ مُسمّاة يُقرأ مفتاحُه من نفسِه: القديمُ
    // مخزَّنٌ ‎96891234567‎ بلا «+»، فتمريرُ دولةٍ مظنونةٍ يُبقيه كلَّه
    // في الحقل بدل أن يُقصّ مفتاحُه إلى المنتقي.
    $national = \App\Support\Phone::national($raw, $chosen);

    $countries = \App\Support\Phone::countries();
    $dark = $theme === 'dark';
    $listDir = app()->getLocale() === 'ar' ? 'rtl' : 'ltr';

    $shell = $dark
        ? 'rounded-xl border border-ivory/15 bg-ink-2/70 focus-within:border-gold/50 focus-within:ring-2 focus-within:ring-gold/20'
        : 'rounded-lg border border-gray-200 bg-white focus-within:ring-2 focus-within:ring-gold-dark focus-within:border-gold/40';
    $sep = $dark ? 'border-ivory/15' : 'border-gray-200';
    $text = $dark ? 'text-ivory' : 'text-gray-900';
    $muted = $dark ? 'text-muted/70' : 'text-gray-500';
    $panel = $dark
        ? 'rounded-xl border border-ivory/15 bg-ink-2 text-ivory shadow-2xl'
        : 'rounded-lg border border-gray-200 bg-white text-gray-900 shadow-xl';
    $row = $dark ? 'hover:bg-ivory/10' : 'hover:bg-gray-100';
@endphp

{{-- حقلُ هاتفٍ دوليّ: منتقي دولةٍ يبحث، وتحقّقٌ بأطوال تلك الدولة.

     ═══ ما كان ═══

     حقلٌ واحدٌ يُكتب فيه كلُّ شيء، وقاعدةٌ تعرف ستَّ دولٍ خليجيّة. فرقمُ
     موكّلٍ سعوديٍّ صحيحٍ يُرَدّ، ورقمُ خصمٍ في الهند يُرَدّ، والرسالةُ
     «رقم هاتف غير صحيح» لا تقول ما العيب ولا كم المطلوب.

     ═══ وما صار ═══

     دولةٌ تُختار من مئتين وخمسٍ وأربعين، وطولُ الرقم يتبعها: ثمانيةٌ
     لعُمان، تسعةٌ للسعوديّة، عشرةٌ للهند. والأطوالُ من بيانات Google
     نفسِها، لا من جدولٍ يشيخ.

     ═══ ويعمل بلا سكربت ═══

     ما يُرسَل من قائمةٍ أصليّةٍ وحقلِ رقم — وهما يعملان بلا جافاسكربت
     ويقرؤهما القارئُ الآليّ. والسكربتُ يُخفي القائمة ويضع مكانها زرّاً
     ولوحةَ بحث، ويكتب اختيارَه في القائمة نفسِها. فإن سقط السكربتُ بقي
     الحقلُ عاملاً، لا معطَّلاً بلا سبب. --}}
<div data-phone-field class="relative">
    @if ($label)
        <label for="{{ $id }}" class="block text-sm font-medium {{ $dark ? 'text-ivory' : 'text-gold-dark' }} mb-2">
            {{ $label }}@if ($required)<span class="text-red-{{ $dark ? '400' : '700' }}"> *</span>@endif
        </label>
    @endif

    <div class="flex items-stretch {{ $shell }} @error($name) border-red-500/60 @enderror" dir="ltr">
        <div class="relative shrink-0 border-e {{ $sep }}">
            {{-- القائمةُ الأصليّة: هي ما يُرسَل، وهي بديلُ اللوحة حين لا سكربت --}}
            <select name="{{ $countryName }}"
                    id="{{ $id }}-country"
                    data-phone-country
                    aria-label="{{ __('app.phone_country') }}"
                    class="h-full appearance-none bg-transparent {{ $text }} text-sm ps-3 pe-7 py-2.5 border-0 focus:ring-0 focus:outline-none max-w-[9rem]">
                @foreach ($countries as $c)
                    <option value="{{ $c['iso'] }}"
                            data-dial="{{ $c['dial'] }}"
                            data-len="{{ implode(',', $c['lengths']) }}"
                            data-max="{{ $c['max'] }}"
                            data-ex="{{ $c['example'] }}"
                            data-flag="{{ $c['flag'] }}"
                            data-main="{{ $c['main'] ? 1 : 0 }}"
                            data-name="{{ $c['name'] }}"
                            data-q="{{ $c['q'] }}"
                            @selected($c['iso'] === $iso)>{{ $c['flag'] }} {{ $c['name'] }} (+{{ $c['dial'] }})</option>
                @endforeach
            </select>

            {{-- ‏display بالسطر لا بصنف: صنفُ hidden وصنفُ flex متساويان
                 في الأولويّة، فأيُّهما يفوز يتبع ترتيبَ الملفّ لا نيّتنا.
                 والسكربتُ يمحو السطرَ فيعود إلى flex الذي في الصنف. --}}
            <button type="button"
                    data-phone-trigger
                    style="display:none"
                    aria-haspopup="listbox"
                    aria-expanded="false"
                    aria-label="{{ __('app.phone_country') }}"
                    class="flex h-full items-center gap-1.5 px-3 py-2.5 text-sm {{ $text }} focus:outline-none focus:ring-2 focus:ring-gold-dark/40 rounded-s-lg">
                <span data-phone-flag class="phone-flag text-base">{{ \App\Support\Phone::flag($iso) }}</span>
                <span data-phone-dial class="font-mono">+{{ \App\Support\Phone::dialCode($iso) }}</span>
                <svg class="w-3 h-3 opacity-50" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                </svg>
            </button>
        </div>

        <input type="tel"
               name="{{ $name }}"
               id="{{ $id }}"
               value="{{ $national }}"
               data-phone-national
               dir="ltr"
               inputmode="tel"
               autocomplete="tel"
               maxlength="{{ \App\Support\Phone::maxLength($iso) }}"
               placeholder="{{ \App\Support\Phone::example($iso) }}"
               @if ($required) required @endif
               {{ $attributes->merge(['class' => 'flex-1 min-w-0 bg-transparent border-0 px-3 py-2.5 text-sm ' . $text . ' placeholder:opacity-40 focus:ring-0 focus:outline-none']) }}>
    </div>

    {{-- اللوحة: تُبنى من خيارات القائمة نفسِها، فلا نسختان تفترقان --}}
    <div data-phone-panel hidden dir="{{ $listDir }}"
         data-row-class="{{ $row }}"
         class="absolute z-50 mt-1 w-full max-w-sm {{ $panel }}">
        <div class="p-2 border-b {{ $sep }}">
            <input type="text" data-phone-search
                   placeholder="{{ __('app.phone_country_search') }}"
                   autocomplete="off"
                   class="w-full rounded-md border {{ $sep }} bg-transparent px-3 py-2 text-sm {{ $text }} placeholder:opacity-40 focus:outline-none focus:ring-2 focus:ring-gold-dark/40">
        </div>
        <ul data-phone-list role="listbox" class="max-h-64 overflow-y-auto py-1 text-sm"></ul>
        <p data-phone-empty hidden class="px-3 py-4 text-center text-xs {{ $muted }}">{{ __('app.phone_country_none') }}</p>
    </div>

    @error($name)
        <p class="mt-1 text-sm text-red-{{ $dark ? '400' : '700' }}">{{ $message }}</p>
    @enderror

    <p data-phone-hint class="mt-1 text-[11px] {{ $muted }} leading-relaxed"></p>

    @if ($note)
        <p class="mt-1 text-[11px] {{ $muted }} leading-relaxed">{{ $note }}</p>
    @endif
</div>

@once
    @include('partials.phone-picker')
@endonce
