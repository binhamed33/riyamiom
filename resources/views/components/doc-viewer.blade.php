<div
    x-data="docViewer()"
    x-on:open-doc-viewer.window="openDoc($event.detail)"
    x-show="viewerOpen"
    x-cloak
    class="fixed inset-0 z-[400] flex items-center justify-center p-4 sm:p-8"
    style="background: rgba(15,23,42,.6);"
    x-on:click.self="viewerOpen = false"
    @keydown.escape.window="viewerOpen = false"
    aria-modal="true"
    role="dialog"
>
    <div class="w-full max-w-5xl bg-white rounded-2xl shadow-2xl flex flex-col overflow-hidden" style="height: min(85vh, 760px);">
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 gap-3" dir="rtl">
            <div class="flex items-center gap-3 min-w-0">
                <h3 class="font-heading font-bold text-gray-900 text-sm truncate" x-text="doc.title"></h3>
                <span class="text-[10px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 font-bold uppercase" x-text="doc.type"></span>
            </div>
            <div class="flex items-center gap-1.5 flex-shrink-0">
                <button type="button" x-on:click="zoom -= 20" :disabled="zoom <= 30" class="w-8 h-8 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold transition disabled:opacity-40">−</button>
                <span class="text-xs font-bold text-gray-500 tabular-nums" x-text="zoom + '%'"></span>
                <button type="button" x-on:click="zoom += 20" :disabled="zoom >= 250" class="w-8 h-8 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold transition disabled:opacity-40">+</button>
                <a :href="doc.download" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-green-500/10 text-green-600 hover:bg-green-500/20 text-xs font-bold transition">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    تحميل
                </a>
                <button type="button" x-on:click="viewerOpen = false" aria-label="{{ __('app.close') }}" class="w-8 h-8 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-500 transition">
                    <svg class="w-4 h-4 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>
        <div class="flex-1 overflow-auto bg-gray-50 p-4 relative" dir="ltr">
            <template x-if="isImage">
                <div class="h-full w-full flex items-center justify-center">
                    <img :src="doc.url" :alt="doc.name || ''" :style="'max-width:' + zoom + '%;'" class="shadow-xl rounded-lg bg-white" />
                </div>
            </template>
            <template x-if="isPdf">
                <div class="h-full w-full flex items-start justify-center">
                    <iframe :src="doc.url" :style="'width:' + zoom + '%;'" class="h-full shadow-xl rounded-lg bg-white border-0"></iframe>
                </div>
            </template>
            <template x-if="!isPdf && !isImage">
                <div class="h-full w-full flex flex-col items-center justify-center gap-3 text-gray-400">
                    <p class="text-sm font-bold">هذا النوع لا يدعم المعاينة المباشرة — قم بالتحميل</p>
                </div>
            </template>
        </div>
        <div class="px-5 py-2.5 border-t border-gray-100 text-[11px] text-gray-400 flex items-center justify-between" dir="rtl">
            <span>يمكنك التنقل بين صفحات PDF عبر شريط أدوات المتصفح داخل المعاينة</span>
            <button type="button" x-on:click="zoom = 100" class="text-xs font-bold text-gold-dark hover:text-gold-dark transition">استعادة الحجم</button>
        </div>
    </div>
</div>

<script nonce="{{ $cspNonce }}">
    function docViewer() {
        return {
            viewerOpen: false,
            zoom: 100,
            doc: { url: '', title: '', type: '', download: '' },
            get isPdf() { return (this.doc.type || '').toLowerCase() === 'pdf'; },
            get isImage() { return ['jpg', 'jpeg', 'png'].includes((this.doc.type || '').toLowerCase()); },
            openDoc(detail) {
                this.doc = {
                    url: detail.url || '',
                    title: detail.title || 'مستند',
                    type: detail.type || '',
                    download: detail.download || detail.url || '',
                };
                this.zoom = 100;
                this.viewerOpen = true;
            },
        };
    }
</script>