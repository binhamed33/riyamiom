<div
    x-data="nlAction('{{ $caseId ?? '' }}')"
    x-on:keydown.escape.window="nlOpen = false"
    x-cloak
>
    <button
        type="button"
        x-on:click="nlOpen = true"
        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-amber-50 border border-amber-100 text-amber-700 hover:bg-amber-100 transition text-sm font-bold"
        :class="nlOpen ? 'bg-amber-100' : ''"
        title="دلني على ما كتبته - يحول النص إلى إجراءات"
    >
        <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/></svg>
        <span class="hidden sm:inline">نص إلى إجراء</span>
        <span class="sm:hidden">تحويل النص</span>
    </button>

    <div x-show="nlOpen" class="fixed inset-0 z-[300] overflow-y-auto" style="background: rgba(15,23,42,.45);" x-on:click="nlOpen = false" aria-modal="true" role="dialog">
        <div class="min-h-full flex items-end sm:items-center justify-center p-0 sm:p-6" x-on:click.stop>
            <div class="w-full sm:max-w-2xl bg-white rounded-t-3xl sm:rounded-2xl shadow-2xl" dir="rtl">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <div>
                        <h3 class="font-heading font-bold text-gray-900">حوّل كلامك إلى إجراءات <span class="text-amber-600">✍️</span></h3>
                        <p class="text-xs text-gray-400 mt-0.5">اكتب طبيعياً — النظام يقترح، وأنت تؤكد قبل التنفيذ</p>
                    </div>
                    <button type="button" x-on:click="nlOpen = false" class="text-gray-400 hover:text-gray-700 transition">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1.5">اكتب ما حصل — مثال: "اتصلت بأحمد اليوم وقال سيرسل صورة البطاقة غداً"</label>
                        <textarea
                            x-model="nlMessage"
                            rows="3"
                            class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100 transition resize-none"
                            placeholder="مثال: كلمت الموكل وسيجهز مذكرة الدفاع غداً، وحددنا موعد جلسة تحضيرية بعد أسبوع"
                        ></textarea>
                    </div>

                    <template x-if="nlError">
                        <div class="text-xs font-medium text-red-600 bg-red-50 rounded-xl px-4 py-2.5" x-text="nlError"></div>
                    </template>

                    <div x-show="!nlParsing && nlActions.length === 0">
                        <button
                            type="button"
                            x-on:click="nlParse()"
                            :disabled="nlMessage.trim() === '' || nlParsing"
                            class="w-full flex items-center justify-center gap-2 py-3 rounded-xl bg-gray-900 text-white text-sm font-bold hover:bg-gray-800 transition disabled:opacity-40"
                        >
                            <svg class="w-4 h-4 animate-spin" x-show="nlParsing" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                            <span x-text="nlParsing ? 'جارٍ الفهم...' : 'اقترح الإجراءات'"></span>
                        </button>
                    </div>

                    <template x-if="nlActions.length > 0">
                        <div class="rounded-2xl border border-amber-100 bg-amber-50/50 overflow-hidden">
                            <div class="px-4 py-3 border-b border-amber-100 text-xs font-bold text-amber-800 bg-amber-50">
                                وجدنا <span x-text="nlActions.length"></span> إجراءاً — أكّد ما تريد تنفيذه:
                            </div>
                            <div class="divide-y divide-amber-100/60 bg-white">
                                <template x-for="(a, i) in nlActions" :key="i">
                                    <label class="flex items-start gap-3 px-4 py-3 cursor-pointer hover:bg-amber-50/40 transition">
                                        <input type="checkbox" x-model="a.selected" class="mt-1 w-4 h-4 accent-amber-600">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                                                <span class="text-[10px] px-2 py-0.5 rounded-full font-bold flex-shrink-0" :class="{
                                                    'bg-red-100 text-red-700': a.type === 'call',
                                                    'bg-emerald-100 text-emerald-700': a.type === 'note',
                                                    'bg-blue-100 text-blue-700': a.type === 'task',
                                                    'bg-violet-100 text-violet-700': a.type === 'appointment'
                                                }">
                                                    <span x-text="{call: 'اتصال', note: 'ملاحظة', task: 'مهمة', appointment: 'موعد'}[a.type] || a.type"></span>
                                                </span>
                                                <input type="text" x-model="a.title" class="flex-1 min-w-0 text-sm font-medium text-gray-800 bg-white border border-transparent hover:border-gray-200 focus:border-amber-300 focus:outline-none rounded-lg px-2 py-1 transition">
                                            </div>
                                            <template x-if="a.due_date">
                                                <p class="text-[11px] text-gray-400 mt-1.5">📅 <span x-text="a.due_date"></span></p>
                                            </template>
                                        </div>
                                    </label>
                                </template>
                            </div>
                        </div>
                    </template>

                    <div class="flex gap-3" x-show="nlActions.length > 0">
                        <button
                            type="button"
                            x-on:click="nlConfirm()"
                            :disabled="nlConfirming || nlActions.filter(a => a.selected).length === 0"
                            class="flex-1 flex items-center justify-center gap-2 py-3 rounded-xl bg-amber-600 text-white text-sm font-bold hover:bg-amber-700 transition disabled:opacity-40"
                        >
                            <svg class="w-4 h-4 animate-spin" x-show="nlConfirming" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                            <span x-text="nlConfirming ? 'جارٍ التنفيذ...' : 'نفّذ (' + nlActions.filter(a => a.selected).length + ')'"></span>
                        </button>
                        <button type="button" x-on:click="nlReset()" class="px-4 py-3 rounded-xl border border-gray-200 text-sm font-bold text-gray-600 hover:bg-gray-50 transition">تعديل</button>
                    </div>

                    <template x-if="nlDone">
                        <div class="flex items-center gap-3 bg-emerald-50 border border-emerald-100 rounded-xl px-4 py-3 text-sm font-bold text-emerald-700">
                            <span x-text="nlDone"></span>
                        </div>
                    </template>
                    <template x-if="nlLink">
                        <div class="flex items-center gap-3">
                            <a :href="nlLink" class="inline-flex items-center gap-1.5 text-xs font-bold text-amber-700 hover:text-amber-900 transition">
                                ← افتح القضية لمشاهدة النتيجة على الخط الزمني
                            </a>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function nlAction(caseId) {
        return {
            nlOpen: false,
            nlMessage: '',
            nlParsing: false,
            nlConfirming: false,
            nlActions: [],
            nlError: '',
            nlDone: '',
            nlLink: '',

            nlParse() {
                if (this.nlMessage.trim() === '') return;
                this.nlParsing = true;
                this.nlError = '';
                this.nlDone = '';
                this.nlLink = '';

                fetch('/nl/actions/parse', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify({ message: this.nlMessage, case_id: caseId || null }),
                })
                    .then(r => r.json().then(j => ({ ok: r.ok, j })))
                    .then(({ ok, j }) => {
                        if (!ok) {
                            this.nlError = j.message || 'تعذر الفهم، حاول صياغة أخرى';
                            return;
                        }
                        this.nlActions = j.actions.map(a => ({ ...a, selected: true }));
                    })
                    .catch(() => { this.nlError = 'خطأ في الاتصال بالخادم'; })
                    .finally(() => { this.nlParsing = false; });
            },

            nlConfirm() {
                const selected = this.nlActions.filter(a => a.selected);
                if (selected.length === 0) return;
                this.nlConfirming = true;
                this.nlError = '';

                fetch('/nl/actions/confirm', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify({
                        case_id: caseId || null,
                        actions: selected.map(a => ({ type: a.type, title: a.title, content: a.content, due_date: a.due_date })),
                    }),
                })
                    .then(r => r.json().then(j => ({ ok: r.ok, j })))
                    .then(({ ok, j }) => {
                        if (!ok) {
                            this.nlError = j.message || 'فشل التنفيذ';
                            return;
                        }
                        this.nlDone = j.message || 'تم التنفيذ';
                        if (j.case_id) this.nlLink = '/cases/' + j.case_id;
                        this.nlActions = [];
                        if (window.dispatchEvent) {
                            window.dispatchEvent(new CustomEvent('nl-actions-created'));
                        }
                    })
                    .catch(() => { this.nlError = 'خطأ في الاتصال بالخادم'; })
                    .finally(() => { this.nlConfirming = false; });
            },

            nlReset() {
                this.nlActions = [];
                this.nlError = '';
                this.nlDone = '';
                this.nlLink = '';
            },
        };
    }
</script>