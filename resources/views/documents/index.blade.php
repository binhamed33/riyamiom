@extends('layouts.app')

@section('title', __('app.page_documents'))

@section('content')
<div class="space-y-6" x-data="{ showUpload: false }">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-900">{{ __('app.page_documents') }} ({{ $documents->total() }})</h2>
        <button @click="showUpload = true"
            class="bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm inline-flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
            </svg>
            {{ __('app.upload_document') }}
        </button>
    </div>
    <div class="flex items-center gap-2">
        @php $currentAccess = request('access_level'); @endphp
        <a href="{{ route('documents.index', array_merge(request()->query(), ['access_level' => ''])) }}"
           class="md-tab px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ !$currentAccess ? 'bg-gold text-[#111827]' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
            {{ __('app.all') }}
        </a>
        <a href="{{ route('documents.index', array_merge(request()->query(), ['access_level' => 'team'])) }}"
           class="md-tab px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ $currentAccess === 'team' ? 'bg-gold text-[#111827]' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
            {{ __('app.access_team') }}
        </a>
        <a href="{{ route('documents.index', array_merge(request()->query(), ['access_level' => 'private'])) }}"
           class="md-tab px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ $currentAccess === 'private' ? 'bg-gold text-[#111827]' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
            {{ __('app.access_private') }}
        </a>

        {{-- فلترة بالنوع: تُنفَّذ في قاعدة البيانات فتصحّ مع الترقيم --}}
        <form method="GET" class="flex items-center gap-2">
            @foreach (request()->except(['doc_type', 'page']) as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
            <select name="doc_type" data-auto-submit
                    class="px-3 py-2 rounded-lg text-sm font-medium bg-gray-100 border-0 text-gray-700 focus:ring-2 focus:ring-gold">
                <option value="">كل الأنواع</option>
                @foreach ($documentTypes as $type)
                    <option value="{{ $type }}" @selected(request('doc_type') === $type)>{{ $type }}</option>
                @endforeach
                @if ($untypedCount > 0)
                    <option value="__untyped__" @selected(request('doc_type') === '__untyped__')>غير محدد ({{ $untypedCount }})</option>
                @endif
            </select>
            <noscript><button class="px-3 py-2 rounded-lg text-sm bg-gray-100">تصفية</button></noscript>
        </form>
    </div>



    <div x-show="showUpload" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true"
         @keydown.escape.window="showUpload = false">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black/50 transition-opacity" @click="showUpload = false"></div>
            <div class="relative bg-white rounded-xl border border-gray-200 max-w-lg w-full p-6 space-y-4 z-10">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('app.new_document') }}</h3>
                    <button @click="showUpload = false" aria-label="{{ __('app.close') }}" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf

                    {{-- الرفعُ يقع حيث تقف: المجلدُ المفتوحُ يُرفَق مع
                         النموذج فيصل المستندُ إليه لا إلى «عام».

                         كان النموذجُ لا يحمل المجلدَ أصلاً، فكلُّ رفعٍ
                         يسقط في «عام» ثم يُنقل يدوياً — وإن بدّل الموظّفُ
                         القضيةَ في القائمة أسقط الخادمُ المجلدَ الغريبَ
                         عنها بصمتٍ ولم يخطئ التصنيف. --}}
                    @if(($currentFolder ?? null) !== null)
                        <input type="hidden" name="case_folder_id" value="{{ $currentFolder->id }}">
                        <p class="text-[11px] text-gold-dark bg-gold/5 border border-gold/20 rounded-lg px-3 py-2">
                            سيُحفظ داخل 📁 «{{ $currentFolder->name }}»
                        </p>
                    @endif

                    <div>
                        <label for="doc_title" class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.title') }} <span class="text-red-500">*</span></label>
                        <input type="text" id="doc_title" name="title" value="{{ old('title') }}"
                               class="w-full rounded-lg bg-white border border-gray-300 text-gray-900 px-3 py-2.5 focus:ring-2 focus:ring-gold focus:border-gold @error('title') border-red-500 @enderror"
                               placeholder="{{ __('app.document_title_placeholder') }}" required>
                        @error('title')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <x-doc-type-field :types="$documentTypes" />

                    <div>
                        <label for="file" class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.document_file') }} <span class="text-red-500">*</span></label>
                        <input type="file" id="file" name="file"
                               class="w-full rounded-lg bg-white border border-gray-300 text-gray-900 px-3 py-2.5 focus:ring-2 focus:ring-gold focus:border-gold @error('file') border-red-500 @enderror"
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" required>
                        <p class="mt-1 text-xs text-gray-500">{{ __('app.allowed_formats') }}</p>
                        @error('file')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="case_id" class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.case') }}</label>
                        <select id="case_id" name="case_id" class="ts w-full rounded-lg bg-white border border-gray-300 text-gray-900 px-3 py-2.5 focus:ring-2 focus:ring-gold focus:border-gold @error('case_id') border-red-500 @enderror">
                            <option value="">{{ __('app.no_case') }}</option>
                            @foreach ($cases as $case)
                                <option value="{{ $case->id }}" {{ old('case_id', $selectedCaseId ?? '') == $case->id ? 'selected' : '' }}>
                                    #{{ $case->office_case_number }} - {{ $case->case_number ?? '' }} - {{ $case->client?->phone ?? '' }} - {{ $case->client?->name ?? '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('case_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- ═══ صاحبُ المستند ═══

                         كان الصاحبُ يُستنتج من القضية وحدَها، فما لا قضيةَ
                         له سقط في «غير منسوبة» مهما عرف الموظّفُ لمن هو:
                         وكالةٌ قبل فتح الملفّ، هويةٌ، عقدٌ لموكّلٍ لم يخاصم
                         أحداً بعد.

                         والخانةُ اختيارية: «بلا نسبة» خيارٌ يُختار لا خانةٌ
                         تُنسى. ومن اختار قضيةً ولم يختر شخصاً بقي صاحبُها
                         مستنتَجاً كما كان. --}}
                    <div>
                        <label for="doc_client_id" class="block text-sm font-medium text-gray-700 mb-1">
                            الشخص <span class="text-gray-400 font-normal text-xs">(اختياري)</span>
                        </label>
                        <select id="doc_client_id" name="client_id" class="ts w-full rounded-lg bg-white border border-gray-300 text-gray-900 px-3 py-2.5 focus:ring-2 focus:ring-gold focus:border-gold @error('client_id') border-red-500 @enderror">
                            <option value="">بلا نسبة — أو يُؤخذ من القضية</option>
                            @foreach ($formClients as $fc)
                                <option value="{{ $fc->id }}" {{ old('client_id', $selectedClientId ?: '') == $fc->id ? 'selected' : '' }}>
                                    {{ $fc->name }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-[11px] text-gray-400">يُحفظ في ملفّ «مستندات ({{ 'اسم الشخص' }})». واتركه فارغاً لتبقى الورقة كما هي.</p>
                        @error('client_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('app.document_access') }} <span class="text-red-500">*</span></label>
                        <div class="flex items-center gap-6">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="access_level" value="all"
                                       {{ old('access_level', 'all') === 'all' ? 'checked' : '' }}
                                       class="w-4 h-4 text-gold focus:ring-gold border-gray-300">
                                <span class="text-sm text-gray-700">{{ __('app.access_public') }}</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="access_level" value="team"
                                       {{ old('access_level') === 'team' ? 'checked' : '' }}
                                       class="w-4 h-4 text-gold focus:ring-gold border-gray-300">
                                <span class="text-sm text-gray-700">{{ __('app.access_team') }}</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="access_level" value="private"
                                       {{ old('access_level') === 'private' ? 'checked' : '' }}
                                       class="w-4 h-4 text-gold focus:ring-gold border-gray-300">
                                <span class="text-sm text-gray-700">{{ __('app.access_private') }}</span>
                            </label>
                        </div>
                        @error('access_level')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">
                            {{ __('app.upload') }}
                        </button>
                        <button type="button" @click="showUpload = false"
                                class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">
                            {{ __('app.cancel') }}
                        </button>
                    </div>
                </form>
            </div>
    </div>
    </div>


    @php
        $__sortOptions = ['created' => __('app.sort_newest'), 'name' => __('app.name'), 'type' => __('app.file_type'), 'size' => __('app.file_size'), 'case' => __('app.case'), 'uploader' => __('app.uploaded_by'), 'access' => __('app.table_access')];
        $__sortDefault = 'created';
    @endphp

    {{-- ═══ منطقةُ الاستبدال ═══
         تبدأ من شريط الترتيب وتنتهي بعد الترقيم: كلُّ ما تغيّره نقرةُ
         ترتيبٍ داخلَها، فلا يبقى شريطٌ يقول «الأحدث» فوق جدولٍ رُتّب
         بالحجم. والقائمةُ الجانبيةُ خارجَها فلا تُرسَم من جديد. --}}
    <div data-live="documents" class="space-y-6">

    {{-- §3: المنجز خلف زرّه + §4: الترتيب --}}
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <x-sort-bar :options="$__sortOptions" :default="$__sortDefault" :default-dir="$__sortDefaultDir ?? 'desc'" />
        <a href="{{ request()->fullUrlWithQuery(['done' => ($done ?? false) ? null : 1, 'page' => null]) }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11px] font-bold border transition {{ ($done ?? false) ? 'bg-gold/12 text-gold-dark border-gold/25' : 'bg-white text-gray-400 border-gray-200 hover:text-gray-600' }}">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ ($done ?? false) ? __('app.show_active') : __('app.show_done') . ' (' . ($doneCount ?? 0) . ')' }}
        </a>
    </div>

    {{-- ═══ طبقةُ الشخص: الجذرُ مجلداتُ الموكّلين ═══

         كانت الشاشةُ تُفتح على كومةٍ واحدةٍ لا يُعرف لمن كلُّ ورقةٍ
         فيها. والآن: جذرٌ فيه «مستندات (فلان)» لكلّ موكّل، ثمّ قضاياه،
         ثمّ مجلداتُ القضية كما كانت.

         والمجلداتُ محسوبةٌ لا محفورةٌ في القاعدة: مجلدٌ باسم موكّلٍ
         يكذب أوّلَ ما يُصحَّح اسمُه، والمحسوبُ يبقى صادقاً بلا صيانة. --}}
    @if(($selectedCaseId ?? 0) === 0 && ($selectedClientId ?? null) === null && !($showAll ?? false))
        @php $clientBase = request()->except(['client_id', 'case_id', 'folder_id', 'page', 'all', 'folder_search']); @endphp

        <div class="mb-5">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                <h2 class="text-sm font-bold text-gray-700">ملفّات الموكّلين</h2>

                <div class="flex items-center gap-2">
                    {{-- بحثٌ في الأسماء: مئتا موكّلٍ لا يُقلَّبون بالعين --}}
                    <form method="GET" action="{{ route('documents.index') }}" class="flex items-center gap-1.5">
                        <input type="search" name="folder_search" value="{{ $folderSearch ?? '' }}"
                               placeholder="ابحث باسم الموكّل…"
                               class="text-xs rounded-lg border border-gray-200 px-3 py-2 w-44 focus:w-56 transition-all">
                        <button class="text-xs px-3 py-2 rounded-lg border border-gray-200 bg-white text-gray-600 hover:border-gold/40">بحث</button>
                    </form>

                    {{-- بابُ الكومة كلِّها: من يبحث عن ورقةٍ لا يعرف صاحبَها --}}
                    <a href="{{ route('documents.index', ['all' => 1]) }}"
                       class="text-xs font-bold px-3 py-2 rounded-lg border border-gray-800 bg-gray-800 text-white hover:bg-black">
                        🗂 كل المستندات
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2.5">
                @forelse($clientFolders ?? [] as $folder)
                    <a href="{{ route('documents.index', $clientBase + ['client_id' => $folder->id]) }}"
                       class="group flex items-center gap-3 p-3 rounded-xl bg-white border border-gray-200 hover:border-gold/50 hover:shadow-sm transition">
                        <span class="shrink-0 w-9 h-9 rounded-lg bg-gold/10 text-gold-dark grid place-items-center text-base">📁</span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-xs font-bold text-gray-800 truncate group-hover:text-gold-dark">{{ $folder->name }}</span>
                            <span class="block text-[11px] text-gray-400">{{ $folder->count }} مستند</span>
                        </span>
                    </a>
                @empty
                    <p class="col-span-full text-xs text-gray-400 py-6 text-center">
                        @if(($folderSearch ?? '') !== '')
                            لا موكّل باسم «{{ $folderSearch }}» له مستندات.
                            <a href="{{ route('documents.index') }}" class="text-gold-dark font-semibold">أظهر الكل</a>
                        @else
                            لا مستنداتٍ منسوبةً إلى موكّلين بعد.
                        @endif
                    </p>
                @endforelse

                @if(($unassignedCount ?? 0) > 0 && ($folderSearch ?? '') === '')
                    <a href="{{ route('documents.index', $clientBase + ['client_id' => 0]) }}"
                       class="group flex items-center gap-3 p-3 rounded-xl bg-gray-50 border border-dashed border-gray-300 hover:border-gray-400 transition">
                        <span class="shrink-0 w-9 h-9 rounded-lg bg-gray-200/70 text-gray-500 grid place-items-center text-base">🗃</span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-xs font-bold text-gray-600 truncate">غير منسوبة</span>
                            <span class="block text-[11px] text-gray-400">{{ $unassignedCount }} مستند</span>
                        </span>
                    </a>
                @endif
            </div>
        </div>
    @endif

    {{-- في وضع «كل المستندات»: طريقُ الرجوع إلى الملفّات ظاهرٌ دائماً --}}
    @if($showAll ?? false)
        <div class="mb-4 flex items-center gap-2">
            <a href="{{ route('documents.index') }}"
               class="text-xs font-bold px-3 py-2 rounded-lg border border-gray-200 bg-white text-gray-600 hover:border-gold/40">
                ← رجوع إلى ملفّات الموكّلين
            </a>
            <span class="text-[11px] text-gray-400">تُعرض الآن كلُّ المستندات بلا تجميع.</span>
        </div>
    @endif

    {{-- داخلَ موكّل: قضاياه مجلداتٍ، ثمّ الدخولُ إلى القضية يعرض مجلداتها --}}
    @if(($selectedClientId ?? 0) > 0 && ($selectedCaseId ?? 0) === 0)
        @php $backBase = request()->except(['client_id', 'case_id', 'folder_id', 'page']); @endphp
        <div class="mb-4 p-3 rounded-xl bg-gray-100 border border-gray-200">
            <div class="flex items-center gap-1.5 flex-wrap">
                <a href="{{ route('documents.index', $backBase) }}"
                   class="text-[11px] font-bold rounded-lg px-2.5 py-1 border bg-white text-gray-500 border-gray-200 hover:text-gray-700">
                    ← كل الأشخاص
                </a>
                <a href="{{ route('documents.index', ['all' => 1]) }}"
                   class="text-[11px] font-bold rounded-lg px-2.5 py-1 border bg-white text-gray-500 border-gray-200 hover:text-gray-700">
                    🗂 كل المستندات
                </a>
                <span class="text-gray-300 text-[11px]">⟵</span>
                <span class="text-[11px] font-bold rounded-lg px-2.5 py-1 border bg-gold/12 text-gold-dark border-gold/25">
                    📁 مستندات ({{ $selectedClient?->name ?? '—' }})
                </span>

                <span class="mx-1 text-gray-200">|</span>

                @forelse($clientCases ?? [] as $case)
                    <a href="{{ route('documents.index', $backBase + ['case_id' => $case->id]) }}"
                       class="text-[11px] font-bold rounded-lg px-2.5 py-1 border transition bg-white text-gray-600 border-gray-200 hover:border-gold/40 hover:text-gray-800">
                        📁 {{ $case->case_number }}
                        <span class="text-gray-400">({{ $case->documents_count }})</span>
                    </a>
                @empty
                    <span class="text-[11px] text-gray-400">لا قضايا لهذا الموكّل.</span>
                @endforelse
            </div>
        </div>
    @endif

    {{-- مجلدات القضية المختارة.
         كانت المجلدات لا تُرى إلا داخل صفحة القضية، فمن جاء يبحث عن ملفاته
         حيث يتوقّعها — صفحة المستندات — لم يعرف أن للنظام مجلدات أصلاً. --}}
    @if(($selectedCaseId ?? 0) > 0)
        @php $folderBase = request()->except(['folder_id', 'page']); @endphp
        <div class="mb-4 p-3 rounded-xl bg-gray-100 border border-gray-200" x-data="{ adding: false }">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-1.5 flex-wrap">
                    {{-- شريطُ الموضع: القضية ⟵ الأب ⟵ المجلد المفتوح.
                         كلُّ حلقةٍ تُنقر فتصعد إليها — فالرجوعُ من عمق
                         الشجرة نقرةٌ لا سلسلةُ «رجوع» --}}
                    <a href="{{ route('documents.index', $folderBase) }}"
                       class="text-[11px] font-bold rounded-lg px-2.5 py-1 border transition {{ ($selectedFolderId ?? null) === null ? 'bg-gold/12 text-gold-dark border-gold/25' : 'bg-white text-gray-500 border-gray-200 hover:text-gray-700' }}">
                        🗂 {{ __('app.all') }}
                    </a>

                    @foreach(($breadcrumb ?? []) as $crumb)
                        <span class="text-gray-300 text-[11px]">⟵</span>
                        <a href="{{ route('documents.index', $folderBase + ['folder_id' => $crumb->id]) }}"
                           class="text-[11px] font-bold rounded-lg px-2.5 py-1 border transition {{ ($currentFolder?->id ?? null) === $crumb->id ? 'bg-gold/12 text-gold-dark border-gold/25' : 'bg-white text-gray-500 border-gray-200 hover:text-gray-700' }}">
                            📁 {{ $crumb->name }}
                        </a>
                    @endforeach

                    <span class="mx-1 text-gray-200">|</span>

                    @if(($currentFolder ?? null) === null)
                        <a href="{{ route('documents.index', $folderBase + ['folder_id' => 0]) }}"
                           class="text-[11px] font-bold rounded-lg px-2.5 py-1 border transition {{ ($selectedFolderId ?? null) === 0 ? 'bg-gold/12 text-gold-dark border-gold/25' : 'bg-white text-gray-500 border-gray-200 hover:text-gray-700' }}">
                            {{ __('app.general') }} ({{ $unfiledCount ?? 0 }})
                        </a>
                    @endif

                    {{-- أبناءُ المستوى المفتوح وحدهم: المكانُ لا يعرض إلا ما فيه --}}
                    @forelse($folders ?? [] as $folder)
                        <a href="{{ route('documents.index', $folderBase + ['folder_id' => $folder->id]) }}"
                           class="text-[11px] font-bold rounded-lg px-2.5 py-1 border transition bg-white text-gray-600 border-gray-200 hover:border-gold/40 hover:text-gray-800">
                            📁 {{ $folder->name }}
                            <span class="text-gray-400">({{ $folder->documents_count }}{{ $folder->children_count > 0 ? ' · ' . $folder->children_count . ' 📁' : '' }})</span>
                        </a>
                    @empty
                        @if(($currentFolder ?? null) !== null)
                            <span class="text-[11px] text-gray-400">لا مجلدات فرعيةً هنا — أنشئ واحداً أو ارفع مستنداً مباشرة</span>
                        @endif
                    @endforelse
                </div>

                @if(in_array(auth()->user()->role, ['developer', 'admin', 'lawyer', 'staff'], true))
                    <button type="button" x-on:click="adding = !adding"
                            class="inline-flex items-center gap-1.5 shrink-0 px-3 py-1.5 rounded-lg border text-xs font-bold transition bg-gold/12 text-gold-dark border-gold/25 hover:bg-gold/20">
                        <svg x-show="!adding" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/>
                        </svg>
                        <svg x-show="adding" x-cloak class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        <span x-show="!adding">{{ __('app.new_folder') }}</span>
                        <span x-show="adding" x-cloak>{{ __('app.cancel') }}</span>
                    </button>
                @endif
            </div>

            @if(in_array(auth()->user()->role, ['developer', 'admin', 'lawyer', 'staff'], true))
                <form x-show="adding" x-cloak method="POST"
                      action="{{ route('case-folders.store', $selectedCaseId) }}"
                      class="mt-3 flex gap-2">
                    @csrf
                    {{-- يولد المجلدُ حيث تقف: داخل المفتوح إن كنتَ داخل واحدٍ --}}
                    @if(($currentFolder ?? null) !== null)
                        <input type="hidden" name="parent_id" value="{{ $currentFolder->id }}">
                    @endif
                    <input type="text" name="name" required maxlength="80"
                           placeholder="{{ ($currentFolder ?? null) ? 'مجلدٌ فرعي داخل «' . $currentFolder->name . '»' : __('app.new_folder_name') }}"
                           class="flex-1 bg-white border border-gray-200 rounded-lg px-3 py-1.5 text-xs text-gray-900 focus:border-gold/40 focus:outline-none">
                    <button class="px-4 py-1.5 rounded-lg bg-gold/12 text-gold-dark border border-gold/25 text-xs font-bold hover:bg-gold/20 transition">
                        {{ __('app.add') }}
                    </button>
                </form>
            @endif
        </div>
    @endif

    {{-- §9: مستكشف الملفات — تفصيلي أو مصغّرات، والاختيار يُحفظ للمستخدم

         واختيارُ العرض محفوظٌ في localStorage يقرأه init بعد الاستبدال،
         فلا يعود من رتّب في «التفصيلي» إلى «المصغّرات». --}}
    <div x-data="{
            view: 'details',
            init() {
                try { this.view = localStorage.getItem('mdDocsView') === 'tiles' ? 'tiles' : 'details'; } catch (e) {}
                this.$watch('view', v => { try { localStorage.setItem('mdDocsView', v); } catch (e) {} });
            }
         }">
        <div class="flex items-center justify-end gap-1.5 mb-3">
            <button type="button" x-on:click="view = 'details'" :aria-pressed="view === 'details' ? 'true' : 'false'"
                    :class="view === 'details' ? 'bg-gold/12 text-gold-dark border-gold/25' : 'bg-white text-gray-400 border-gray-200 hover:text-gray-600'"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border text-[11px] font-bold transition"
                    title="{{ __('app.view_details') }}" aria-label="{{ __('app.view_details') }}">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                {{ __('app.details') }}
            </button>
            <button type="button" x-on:click="view = 'tiles'" :aria-pressed="view === 'tiles' ? 'true' : 'false'"
                    :class="view === 'tiles' ? 'bg-gold/12 text-gold-dark border-gold/25' : 'bg-white text-gray-400 border-gray-200 hover:text-gray-600'"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border text-[11px] font-bold transition"
                    title="{{ __('app.view_thumbnails') }}" aria-label="{{ __('app.view_thumbnails') }}">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h6v6H4zM14 5h6v6h-6zM4 13h6v6H4zM14 13h6v6h-6z"/></svg>
                {{ __('app.thumbnails') }}
            </button>
        </div>

    <div x-show="view === 'tiles'" x-cloak class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 mb-4">
        @forelse ($documents as $document)
            @php
                $ext = strtolower($document->file_type ?? '');
                $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                $isPdf = $ext === 'pdf';
                $previewable = in_array($ext, ['pdf', 'jpg', 'jpeg', 'png']);
            @endphp
            <div class="bg-white rounded-xl border border-gray-200 hover:border-gold/25 transition-colors overflow-hidden">
                <div class="aspect-[4/3] bg-gray-100 flex items-center justify-center overflow-hidden relative">
                    @if($isImage)
                        <img src="{{ route('documents.preview', $document) }}" alt="{{ $document->title }}" loading="lazy" class="w-full h-full object-cover">
                    @elseif($isPdf)
                        {{-- صفحةُ الـPDF الأولى معاينةً، لا أيقونةً حمراء.
                             المتصفّح يرسم الـPDF بنفسه فلا تلزم مكتبة، والمسار
                             هو `preview` نفسه الذي يفحص الصلاحية قبل البثّ.

                             ولا تُحمَّل إلا حين تدخل الشاشة: خمسةَ عشرَ ملفاً
                             تُحمَّل كاملةً عند فتح الصفحة تُثقلها ثقلاً بيّناً،
                             وأكثرها لا يراه أحد. و`pointer-events` مقفلة فالنقر
                             يفتح العارض لا يتصفّح داخل المربّع الصغير. --}}
                        <div class="absolute inset-0 md-pdf-thumb bg-white"
                             data-pdf-src="{{ route('documents.preview', $document) }}#page=1&view=FitH&toolbar=0&navpanes=0&scrollbar=0"
                             aria-hidden="true"></div>
                        <svg class="w-10 h-10 text-red-300 md-pdf-fallback" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                    @else
                        <svg class="w-10 h-10 {{ $ext === 'pdf' ? 'text-red-400' : (str_contains($ext, 'doc') ? 'text-blue-400' : (str_contains($ext, 'xls') ? 'text-green-500' : 'text-gray-300')) }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                    @endif
                </div>
                <div class="p-2.5">
                    <p class="text-xs font-medium text-gray-900 truncate" title="{{ $document->title }}">{{ $document->title }}</p>
                    <p class="text-[11px] text-gray-400 mt-0.5" style="font-variant-numeric: tabular-nums">
                        {{ $document->created_at?->format('Y-m-d') }} · {{ strtoupper($ext) }}
                    </p>
                    <div class="flex items-center gap-1 mt-2">
                        @if($previewable)
                            <button type="button" x-on:click="$dispatch('open-doc-viewer', { url: '{{ route('documents.preview', $document) }}', title: '{{ addslashes($document->title) }}', type: '{{ $document->file_type }}', download: '{{ route('documents.download', $document) }}' })"
                                    class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-purple-500/10 text-purple-500 hover:bg-purple-500/20 transition" title="{{ __('app.preview') }}">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </button>
                        @endif
                        <a href="{{ route('documents.download', $document) }}" class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-green-500/10 text-green-600 hover:bg-green-500/20 transition" title="{{ __('app.download') }}">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full">
                <x-empty-state :title="__('app.no_documents')" :hint="__('app.no_documents_hint')" icon="documents"
                               :filtered="($activeFilters ?? 0) > 0" :clear-url="url()->current()" />
            </div>
        @endforelse
    </div>

    <div x-show="view === 'details'" class="bg-white rounded-xl border border-gray-200">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-gold-dark">
                <tr>
                    @php
                        $__s = request('sort', 'created');
                        $__d = request('dir', in_array($__s, ['name', 'case', 'uploader'], true) ? 'asc' : 'desc');
                    @endphp
                    <x-th-sort key="name" :label="__('app.title')" :sort="$__s" :dir="$__d" />
                    <x-th-sort key="case" :label="__('app.case')" :sort="$__s" :dir="$__d" />
                    <x-th-sort key="uploader" :label="__('app.uploaded_by')" :sort="$__s" :dir="$__d" />
                    <x-th-sort key="type" :label="__('app.type')" :sort="$__s" :dir="$__d" />
                    <x-th-sort key="size" :label="__('app.table_size')" :sort="$__s" :dir="$__d" />
                    <x-th-sort key="access" :label="__('app.table_access')" :sort="$__s" :dir="$__d" />
                    <x-th-sort key="created" :label="__('app.date')" :sort="$__s" :dir="$__d" />
                    <th class="px-6 py-3 text-right font-semibold">{{ __('app.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($documents as $document)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 font-medium text-gray-900">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 {{ str_contains($document->file_type ?? '', 'pdf') ? 'text-red-500' : (str_contains($document->file_type ?? '', 'doc') ? 'text-blue-500' : (str_contains($document->file_type ?? '', 'xls') ? 'text-green-500' : (str_contains($document->file_type ?? '', 'jpg') || str_contains($document->file_type ?? '', 'jpeg') || str_contains($document->file_type ?? '', 'png') ? 'text-purple-500' : 'text-gray-400'))) }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                </svg>
                                <div class="min-w-0">
                                    <p class="truncate">{{ $document->title }}</p>
                                    @if(!$document->doc_type)
                                        <span class="bg-gray-100 border border-gray-200 rounded-full px-2 py-0.5 text-gray-500">غير محدد</span>
                                    @endif
                                    @if($document->doc_type)
                                        <p class="text-[11px] font-bold text-gold-dark mt-0.5 flex items-center gap-1.5">
                                            <span class="bg-gold/10 border border-gold/15 rounded-full px-2 py-0.5">{{ $document->doc_type }}</span>
                                            @if($document->doc_date)<span class="text-gray-400 font-normal">📅 {{ $document->doc_date->format('Y/m/d') }}</span>@endif
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-gray-600">{{ $document->case->title ?? '—' }}</td>
                        <td class="px-6 py-4 text-gray-600">{{ $document->uploader->name ?? '—' }}</td>
                        <td class="px-6 py-4">
                            <span class="uppercase text-xs font-semibold text-gray-500 bg-gray-100 px-2 py-0.5 rounded">{{ strtoupper($document->file_type ?? '') }}</span>
                        </td>
                        <td class="px-6 py-4 text-gray-600">{{ round(($document->file_size ?? 0) / 1024, 1) }} KB</td>
                        <td class="px-6 py-4">
                            @if (($document->access_level ?? '') === 'all')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-500/20 text-green-400">{{ __('app.access_public') }}</span>
                            @elseif (($document->access_level ?? '') === 'team')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-500/20 text-blue-400">{{ __('app.access_team') }}</span>
                            @elseif (($document->access_level ?? '') === 'private')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-500/20 text-red-400">{{ __('app.access_private') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-gray-600">
                            @if ($document->created_at){{ $document->created_at->format('Y-m-d') }}@endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-1">
                                @php $previewable = in_array(strtolower($document->file_type ?? ''), ['pdf', 'jpg', 'jpeg', 'png']) @endphp
                                @if($previewable)
                                <button type="button" x-on:click="$dispatch('open-doc-viewer', { url: '{{ route('documents.preview', $document) }}', title: '{{ addslashes($document->title) }}', type: '{{ $document->file_type }}', download: '{{ route('documents.download', $document) }}' })" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-purple-500/10 text-purple-400 hover:bg-purple-500/20 transition-colors" title="{{ __('app.preview') }}">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </button>
                                @endif
                                <a href="{{ route('documents.download', $document) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-green-500/10 text-green-400 hover:bg-green-500/20 transition-colors" title="{{ __('app.download') }}">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                                </a>
                                <form method="POST" action="{{ route('documents.destroy', $document) }}" class="contents" x-data x-ref="deleteForm" @submit.prevent="if(confirm('{{ __('app.confirm_delete') }}')) $el.submit()">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-500/10 text-red-400 hover:bg-red-500/20 transition-colors" title="{{ __('app.delete') }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                        <tr>
                            <td colspan="8" class="p-0">
                                <x-empty-state
                                    :title="__('app.no_documents')"
                                    :hint="__('app.no_documents_hint')"
                                    icon="documents"
                                    :filtered="($activeFilters ?? 0) > 0"
                                    :clear-url="url()->current()" />
                            </td>
                        </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    @if ($documents->hasPages())
        <div class="mt-4">
            <div data-live-nav>{{ $documents->links() }}</div>
        </div>
    @endif
    </div>

    </div>{{-- /منطقةُ الاستبدال --}}
</div>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce }}">
document.addEventListener('DOMContentLoaded', function () {
    var boxes = document.querySelectorAll('.md-pdf-thumb[data-pdf-src]');
    if (!boxes.length) return;

    // الإطار يُنشأ حين تدخل البطاقةُ الشاشة لا قبلها: خمسةَ عشرَ ملفاً
    // تُحمَّل كاملةً عند فتح الصفحة تُثقلها ثقلاً بيّناً، وأكثرها لا يراه أحد.
    function mount(box) {
        if (box.dataset.mounted) return;
        box.dataset.mounted = '1';

        var frame = document.createElement('iframe');
        frame.src = box.dataset.pdfSrc;
        frame.loading = 'lazy';
        frame.tabIndex = -1;
        frame.setAttribute('aria-hidden', 'true');
        frame.style.cssText = 'width:100%;height:100%;border:0;pointer-events:none;background:#fff';

        // متصفّحٌ لا يعرض PDF داخلياً يُبقي الأيقونة بدل مربّعٍ أبيض فارغ
        frame.addEventListener('error', function () {
            box.remove();
        });

        box.appendChild(frame);

        var icon = box.parentElement && box.parentElement.querySelector('.md-pdf-fallback');
        if (icon) icon.style.display = 'none';
    }

    if (!('IntersectionObserver' in window)) {
        boxes.forEach(mount);
        return;
    }

    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                mount(entry.target);
                io.unobserve(entry.target);
            }
        });
    }, { rootMargin: '200px' });

    boxes.forEach(function (b) { io.observe(b); });
});
</script>
@endpush
