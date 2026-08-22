@props(['action', 'count' => 0, 'clearUrl' => null, 'hidden' => []])

@once
@push('styles')
<style>
    /* ===== لوحة الفلترة الموحّدة ===== */
    .md-chip { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.6rem;
        border-radius: 999px; font-size: 0.72rem; font-weight: 700; line-height: 1.6;
        background: var(--accent-a12); color: var(--accent-dark); border: 1px solid var(--accent-a30);
        transition: background 0.2s, border-color 0.2s; max-width: 100%; }
    .md-chip:hover { background: var(--accent-a20); }
    .md-chip .md-chip-x { font-size: 0.85rem; line-height: 1; opacity: 0.65; }
    .md-chip:hover .md-chip-x { opacity: 1; }
    .md-chip:focus-visible { outline: 2px solid var(--accent-dark); outline-offset: 2px; }
    .md-chip-val { max-width: 16ch; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    /* على الهاتف تُفتح الفلاتر كورقة سفلية لا كقائمة صغيرة */
    @media (max-width: 767px) {
        .md-filter-sheet { position: fixed; inset-inline: 0; bottom: 0; z-index: 60;
            max-height: 88vh; display: flex; flex-direction: column;
            border-radius: 22px 22px 0 0; border-top: 1px solid var(--accent-a20);
            box-shadow: 0 -18px 50px rgba(0,0,0,0.22); background: #FFFFFF; }
        [data-theme="dark"] .md-filter-sheet { background: #121826; }
        .md-filter-sheet .md-filter-body { overflow-y: auto; -webkit-overflow-scrolling: touch; padding-bottom: 0.5rem; }
        .md-filter-backdrop { position: fixed; inset: 0; z-index: 55; background: rgba(9,12,18,0.45); backdrop-filter: blur(2px); }
        .md-filter-grab { width: 42px; height: 4px; border-radius: 999px; background: #D8DCE3; margin: 10px auto 2px; }
        [data-theme="dark"] .md-filter-grab { background: #333C4E; }
        /* مساحة لمس مريحة للحقول داخل الورقة */
        .md-filter-sheet select, .md-filter-sheet input { min-height: 44px; font-size: 16px; }
        /* زر التطبيق يبقى في المتناول مهما طالت قائمة الفلاتر */
        .md-filter-sheet button[type="submit"] { min-height: 48px; position: sticky; bottom: 0; }
        .md-filter-sheet form > * > div:has(> button[type="submit"]),
        .md-filter-sheet form div:has(> button[type="submit"]) {
            position: sticky; bottom: -1rem; background: inherit; padding-block: 0.75rem; z-index: 2; }
    }
    @media (min-width: 768px) {
        .md-filter-grab, .md-filter-backdrop { display: none !important; }
    }
</style>
@endpush
@endonce

<div class="bg-white rounded-xl border border-gold/15 overflow-visible"
     x-data="{
        open: {{ $count > 0 ? 'true' : 'false' }},
        chips: [],
        isMobile: window.matchMedia('(max-width: 767px)').matches,
        init() {
            // نلتقط الفلاتر المطبَّقة كما رسمها الخادم، لا التعديلات غير المحفوظة
            this.$nextTick(() => this.readChips());
            window.addEventListener('resize', () => { this.isMobile = window.matchMedia('(max-width: 767px)').matches; });
            if (this.isMobile) this.open = false;
        },
        readChips() {
            const form = this.$refs.form;
            if (!form) return;
            const out = [];
            form.querySelectorAll('[name]').forEach(el => {
                if (el.type === 'hidden' || el.type === 'submit' || !el.value) return;
                const wrap = el.closest('div');
                const lab = wrap ? wrap.querySelector('label') : null;
                let text = el.value;
                if (el.tagName === 'SELECT' && el.selectedIndex >= 0) text = el.options[el.selectedIndex].text.trim();
                out.push({ name: el.name, label: lab ? lab.textContent.trim() : el.name, value: text });
            });
            this.chips = out;
        },
        removeChip(chip) {
            const form = this.$refs.form;
            const el = form.querySelector('[name=\'' + chip.name + '\']');
            if (!el) return;
            el.value = '';
            form.submit();
        },
        toggle() { this.open = !this.open; }
     }">

    <div class="flex items-center justify-between gap-3 px-4 py-1.5">
        <button type="button" @click="toggle()"
                class="flex-1 flex items-center gap-2.5 py-2 text-sm font-bold text-gold-dark hover:opacity-80 transition"
                :aria-expanded="open ? 'true' : 'false'">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18l-7 8v6l-4 2v-8L3 4z"/>
            </svg>
            {{ __('app.filter') }}
            @if($count > 0)
                <span class="min-w-[20px] h-5 px-1.5 inline-flex items-center justify-center rounded-full bg-gold text-gray-900 text-[11px] font-bold">{{ $count }}</span>
            @endif
            <svg class="w-4 h-4 flex-shrink-0 text-gray-400 transition-transform duration-200 md:block hidden" :class="open ? 'rotate-180' : ''"
                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
        @if($count > 0 && $clearUrl)
            <a href="{{ $clearUrl }}" class="text-xs font-medium text-gray-500 hover:text-red-600 transition whitespace-nowrap">
                ✕ {{ __('app.clear_filters') }}
            </a>
        @endif
    </div>

    {{-- الفلاتر المطبَّقة: كل واحدة تُزال وحدها --}}
    <div x-show="chips.length > 0" x-cloak class="px-4 pb-3 pt-0.5 flex flex-wrap gap-1.5 items-center border-t border-gray-100">
        <span class="text-[11px] font-bold text-gray-400 me-1">{{ __('app.active_filters') }}</span>
        <template x-for="chip in chips" :key="chip.name">
            <button type="button" class="md-chip" @click="removeChip(chip)"
                    :aria-label="'{{ __('app.remove_filter') }} ' + chip.label">
                <span x-text="chip.label + ':'"></span>
                <span class="md-chip-val font-extrabold" x-text="chip.value"></span>
                <span class="md-chip-x" aria-hidden="true">✕</span>
            </button>
        </template>
    </div>

    {{-- خلفية معتمة خلف الورقة السفلية على الهاتف --}}
    <div x-show="open && isMobile" x-cloak class="md-filter-backdrop" @click="open = false" x-transition.opacity></div>

    <div x-show="open" x-cloak
         :class="isMobile ? 'md-filter-sheet' : 'border-t border-gray-100'"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 md:-translate-y-1 translate-y-6"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 md:-translate-y-1 translate-y-6">

        <div class="md-filter-grab" aria-hidden="true"></div>
        <div x-show="isMobile" x-cloak class="flex items-center justify-between px-4 pt-1 pb-3 border-b border-gray-100">
            <span class="font-bold text-gray-800">{{ __('app.filter') }}</span>
            <button type="button" @click="open = false" class="p-2 -m-2 text-gray-400" aria-label="{{ __('app.close') }}">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form method="GET" action="{{ $action }}" class="p-4 md-filter-body" x-ref="form">
            @foreach($hidden as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            {{ $slot }}
        </form>
    </div>
</div>
