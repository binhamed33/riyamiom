@php
    $_ar = app()->getLocale() === 'ar';
    $palMsgNoResults = $_ar ? 'لا توجد نتائج مطابقة' : 'No matching results';
    $palMsgStartTyping = $_ar ? 'ابدأ الكتابة...' : 'Start typing...';
    $palGroupTitles = $_ar
        ? ['case' => 'القضايا', 'client' => 'الموكلون', 'session' => 'الجلسات', 'task' => 'المهام', 'document' => 'المستندات', 'invoice' => 'الفواتير', 'activity' => 'النشاطات', 'recent-case' => 'أحدث القضايا', 'recent-session' => 'الجلسات القادمة', 'recent-task' => 'مهام متأخرة']
        : ['case' => 'Cases', 'client' => 'Clients', 'session' => 'Sessions', 'task' => 'Tasks', 'document' => 'Documents', 'invoice' => 'Invoices', 'activity' => 'Activities', 'recent-case' => 'Recent cases', 'recent-session' => 'Upcoming sessions', 'recent-task' => 'Overdue tasks'];
@endphp
{{-- Command Palette - Lawyer OS --}}
<div x-data="commandPalette()" class="relative min-w-0 flex-1 max-w-md">
    {{-- Trigger (looks like the old search box, opens overlay) --}}
    <button type="button" @click="openPalette()" class="w-full flex items-center gap-2.5 bg-gray-100 border border-gray-200 rounded-xl {{ $_ar ? 'pr-3 pl-2' : 'pl-3 pr-2' }} py-2 text-sm text-gray-400 hover:border-gold/40 hover:bg-white transition-all group" aria-label="{{ __('app.search') }}">
        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
        <span class="truncate font-heading text-[13px] group-hover:text-gray-600 transition-colors">{{ __('app.search') }}...</span>
        <kbd class="ms-auto hidden sm:inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-md border border-gray-200 bg-white text-[10px] font-bold text-gray-400 shadow-sm"><span>Ctrl</span><span>K</span></kbd>
    </button>

    {{-- Overlay --}}
    <template x-teleport="body">
    <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" @keydown.escape.window="open = false" class="fixed inset-0 z-[120]" style="background: rgba(10,8,5,0.55); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);" @click="if($event.target === $el) open = false" role="dialog" aria-modal="true" aria-label="{{ __('app.search') }}">
        <div class="absolute inset-x-0 top-[8vh] sm:top-[12vh] mx-auto w-[94vw] sm:max-w-xl" @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)">
            <div class="rounded-2xl overflow-hidden border border-gold-light/25 bg-gradient-to-b from-[#121826] to-[#080B12] shadow-[0_40px_120px_-20px_rgba(0,0,0,0.65),inset_0_1px_0_rgba(212,175,55,0.12)]">
                <div class="absolute inset-x-0 top-0 h-[2px] bg-gradient-to-l from-gold-dark/60 via-gold/70 to-gold-light/60 pointer-events-none"></div>

                {{-- Input row --}}
                <div class="flex items-center gap-3 px-5" style="border-bottom: 1px solid rgba(212,175,55,0.12);">
                    <svg class="w-5 h-5 text-gold-light/80 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                    <input x-ref="palInput" type="text" x-model="query" @input.debounce.250ms="run()" @keydown.enter="go()" autocomplete="off" autocapitalize="off" spellcheck="false" placeholder="{{ app()->getLocale() === 'ar' ? 'ابحث عن قضية، موكل، جلسة، مهمة... أو ابدأ بـ  >  للأوامر' : 'Search cases, clients, sessions... or start with  >  for commands' }}" class="w-full bg-transparent py-4 text-[15px] text-gold-light placeholder-gold-light/40 focus:outline-none">
                    <button type="button" @click="open = false" class="shrink-0 flex items-center gap-1 px-2 py-1 rounded-lg text-[10px] font-bold text-gold-light/50 hover:text-gold-light hover:bg-gold-light/10 transition" aria-label="{{ app()->getLocale() === 'ar' ? 'إغلاق' : 'Close' }}">
                        ESC
                    </button>
                </div>

                {{-- Loading --}}
                <div x-show="loading" x-cloak class="px-5 py-8 flex items-center justify-center gap-3 text-gold-light/60 text-sm">
                    <svg class="w-5 h-5 animate-spin text-gold-light" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                    <span>{{ app()->getLocale() === 'ar' ? 'جارٍ البحث...' : 'Searching...' }}</span>
                </div>

                {{-- Results --}}
                <div x-show="!loading" class="max-h-[52vh] overflow-y-auto overscroll-contain" style="border-bottom: 1px solid rgba(251,191,36,0.1);">
                    {{-- Commands section --}}
                    <div x-show="actions.length > 0">
                        <div class="px-5 pt-3 pb-1.5 flex items-center gap-2">
                            <span class="text-[10px] font-bold tracking-[0.2em] text-gold-light/60 uppercase">{{ app()->getLocale() === 'ar' ? 'أوامر سريعة' : 'Quick commands' }}</span>
                            <span class="flex-1 h-px bg-gold-light/10"></span>
                        </div>
                        <template x-for="(a, i) in actions" :key="'a'+a.key">
                            <a :href="a.url" @click="open = false" class="flex items-center gap-3 px-5 py-2.5 text-sm transition-all duration-150" :class="isActive('action', i) ? 'bg-gold-light/15 text-gold-light' : 'text-gold-light/70 hover:bg-gold-light/5 hover:text-gold-light'" @mouseenter="setActive('action', i)">
                                <span class="w-6 h-6 shrink-0 rounded-lg bg-gold-light/10 border border-gold-light/20 flex items-center justify-center text-[11px] font-bold text-gold-light" x-text="a.icon"></span>
                                <span x-text="a.label" class="truncate"></span>
                            </a>
                        </template>
                    </div>

                    {{-- Search groups --}}
                    <template x-for="key in groupKeys" :key="key">
                        <div>
                            <div class="px-5 pt-3 pb-1.5 flex items-center gap-2">
                                <span class="text-[10px] font-bold tracking-[0.2em] text-gold-light/60 uppercase" x-text="groupTitle(key)"></span>
                                <span class="flex-1 h-px bg-gold-light/10"></span>
                            </div>
                            <template x-for="(r, j) in groups[key]" :key="key+j">
                                <a :href="r.url" @click="open = false" class="flex items-center gap-3 px-5 py-2.5 text-sm transition-all duration-150" :class="isActive('result', key, j) ? 'bg-gold-light/15 text-gold-light' : 'text-gold-light/70 hover:bg-gold-light/5 hover:text-gold-light'" @mouseenter="setActive('result', key, j)">
                                    <span class="w-6 h-6 shrink-0 rounded-lg bg-gold-light/10 border border-gold-light/20 flex items-center justify-center text-[11px] font-bold text-gold-light" x-text="r.icon"></span>
                                    <span class="min-w-0">
                                        <span class="block truncate" x-text="r.label"></span>
                                        <span x-show="r.sub" class="block text-[11px] text-gold-light/40 truncate" x-text="r.sub"></span>
                                    </span>
                                </a>
                            </template>
                        </div>
                    </template>

                    {{-- Empty --}}
                    <div x-show="empty" class="px-5 py-10 text-center">
                        <div class="w-12 h-12 mx-auto rounded-full bg-gold-light/5 border border-gold-light/15 flex items-center justify-center mb-3">
                            <svg class="w-5 h-5 text-gold-light/50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                        </div>
                        <p class="text-sm text-gold-light/50" x-text="query.length > 1 ? '{{ $palMsgNoResults }}' : '{{ $palMsgStartTyping }}'"></p>
                    </div>
                </div>

                {{-- Footer hints --}}
                <div class="flex items-center gap-4 px-5 py-2.5 text-[10px] text-gold-light/35" style="border-top: 1px solid rgba(251,191,36,0.1);">
                    <span class="flex items-center gap-1"><kbd class="px-1 leading-4 rounded border border-gold-light/20 bg-gold-light/5">↑</kbd><kbd class="px-1 leading-4 rounded border border-gold-light/20 bg-gold-light/5">↓</kbd> {{ app()->getLocale() === 'ar' ? 'تنقل' : 'Navigate' }}</span>
                    <span class="flex items-center gap-1"><kbd class="px-1 leading-4 rounded border border-gold-light/20 bg-gold-light/5">Enter</kbd> {{ app()->getLocale() === 'ar' ? 'فتح' : 'Open' }}</span>
                    <span class="flex items-center gap-1"><kbd class="px-1 leading-4 rounded border border-gold-light/20 bg-gold-light/5">&gt;</kbd> {{ app()->getLocale() === 'ar' ? 'أوامر فقط' : 'Commands only' }}</span>
                </div>
            </div>
        </div>
    </div>
    </template>
</div>

@push('scripts')
<script nonce="{{ $cspNonce }}">
function commandPalette() {
    return {
        open: false,
        query: '',
        loading: false,
        groups: {},
        actions: [],
        empty: true,
        flat: [],
        activeIndex: -1,

        init() {
            const self = this;
            document.addEventListener('keydown', function(e) {
                const k = e.key.toLowerCase();
                if ((e.ctrlKey || e.metaKey) && k === 'k') {
                    e.preventDefault();
                    self.toggle();
                } else if ((e.ctrlKey || e.metaKey) && e.shiftKey && k === 'p') {
                    e.preventDefault();
                    self.toggle(true);
                }
            });
        },

        toggle(commandsOnly) {
            this.open = !this.open;
            if (this.open) {
                this.query = commandsOnly ? '>' : '';
                this.run();
                this.focusInput();
            }
        },

        openPalette() {
            this.open = true;
            this.query = '';
            this.run();
            this.focusInput();
        },

        focusInput() {
            this.$nextTick(function() {
                var el = document.querySelector('.fixed.inset-0.z-\\[120\\] input');
                if (el) el.focus();
            });
        },

        run() {
            const self = this;
            const q = this.query;
            const cmdMode = q.startsWith('>');

            if (cmdMode) {
                const t = q.slice(1).toLowerCase();
                const all = self.actions;
                self.groups = {};
                self.flat = all.filter(function(a) {
                    return !t || a.label.toLowerCase().indexOf(t) !== -1 || a.key.toLowerCase().indexOf(t) !== -1;
                }).map(function(a, i) { return { kind: 'action', idx: i, url: a.url, label: a.label }; });
                self.empty = self.flat.length === 0;
                self.activeIndex = -1;
                return;
            }

            if (q.length < 2) {
                this.loading = true;
                fetch('/command')
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        self.groups = data.groups || {};
                        self.loading = false;
                        self.activeIndex = -1;
                        self.rebuild();
                    })
                    .catch(function() { self.loading = false; });
                return;
            }

            this.loading = true;
            fetch('/command?q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    self.groups = data.groups || {};
                    self.actions = data.actions || [];
                    self.loading = false;
                    self.activeIndex = -1;
                    self.rebuild();
                })
                .catch(function() { self.loading = false; });
        },

        rebuild() {
            const list = [];
            const aIdx = {};
            const self = this;
            if (this.actions.length) {
                this.actions.forEach(function(a, i) {
                    aIdx['action' + i] = list.length;
                    list.push({ kind: 'action', idx: i, gkey: null, rindex: null, url: a.url, label: a.label });
                });
            }
            Object.keys(this.groups).forEach(function(key) {
                self.groups[key].forEach(function(r, j) {
                    list.push({ kind: 'result', idx: j, gkey: key, rindex: j, url: r.url, label: r.label, sub: r.sub });
                });
            });
            this.flat = list;
            this.empty = list.length === 0;
        },

        get groupKeys() {
            return Object.keys(this.groups);
        },

        setActive(kind, keyOrIdx, j) {
            const targetKey = kind === 'action' ? ('action' + keyOrIdx) : ('result' + keyOrIdx + '_' + (j === undefined ? 0 : j));
            let target = -1;
            this.flat.forEach(function(f, i) {
                const key = kind === 'action' ? ('action' + f.idx) : ('result' + f.gkey + '_' + f.rindex);
                if (key === targetKey) target = i;
            });
            this.activeIndex = target;
        },

        isActive(kind, keyOrIdx, j) {
            const f = this.flat[this.activeIndex];
            if (!f || f.kind !== kind) return false;
            return kind === 'action' ? f.idx === keyOrIdx : (f.gkey === keyOrIdx && f.rindex === j);
        },

        move(dir) {
            if (this.flat.length === 0) return;
            this.activeIndex = (this.activeIndex + dir + this.flat.length) % this.flat.length;
        },

        go() {
            const f = this.flat[this.activeIndex];
            if (f && f.url) window.location.href = f.url;
            else if (this.flat.length && this.flat[0].url) window.location.href = this.flat[0].url;
        },

        groupTitle(key) {
            return @json($palGroupTitles)[key] || key;
        }
    };
}
</script>
@endpush