<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\LegalCase;
use App\Traits\AuditLoggable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    use AuditLoggable;
    private const ALLOWED_TYPES = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png',
    ];

    private const MAX_SIZE = 20 * 1024 * 1024;

    public function index(Request $request): View
    {
        $user = auth()->user();

        // القاعدةُ نفسُها في القائمة وفي كلّ عددٍ حولها (Document::visibleTo).
        // كانت القائمةُ وحدها تُرشَّح والأعدادُ تُحسب على الجدول كلِّه، فيقرأ
        // المديرُ «14 مستنداً» فوق قائمةٍ من ثلاثة عشر — والرابعَ عشرَ مستندٌ
        // خاصٌّ بزميلةٍ صار العددُ يفضح وجودَه.
        $visible = fn ($q) => $q->visibleTo($user);

        $query = Document::with(['case', 'uploader'])->visibleTo($user);

        // مستندات القضايا المنجزة تُطوى خلف زر «المنجزة» — وما لا قضية له يبقى
        $done = $request->boolean('done');
        if ($done) {
            $query->whereHas('case', fn ($q) => $q->whereIn('status', \App\Models\LegalCase::DONE_STATUSES));
        } elseif (!$request->filled('case_id')) {
            $query->where(function ($q) {
                $q->whereNull('case_id')
                    ->orWhereHas('case', fn ($c) => $c->whereNotIn('status', \App\Models\LegalCase::DONE_STATUSES));
            });
        }

        if ($request->filled('case_id')) {
            $query->where('case_id', $request->case_id);
        }

        // ═══ طبقةُ الشخص فوق القضايا ═══
        //
        // المستندُ يعيش تحت قضية، والقضيةُ لموكّل — فمستنداتُ الشخص
        // معروفةٌ ضمناً ولا يراها أحد: الشاشةُ كانت تفتح على كومةٍ
        // واحدةٍ لا يُعرف لمن كلُّ ورقةٍ فيها.
        //
        // ولا تُنشأ مجلداتٌ باسم الأشخاص في القاعدة: مجلدٌ محفورٌ باسم
        // موكّلٍ يكذب أوّلَ ما يُصحَّح اسمُه أو تُنقل قضيتُه، ويلزمه
        // نقلُ آلافِ الصفوف في مكاتبَ تعمل. فالتجميعُ يُحسب من الرابط
        // القائم (مستند ⇐ قضية ⇐ موكّل) ويبقى صادقاً بلا صيانة.
        //
        // و«0» تعني «بلا موكّل» — وهي قيمةٌ مقصودة لا غياب.
        $selectedClientId = $request->has('client_id') && $request->input('client_id') !== ''
            ? (int) $request->input('client_id')
            : null;

        // النسبتان معاً: ما كُتب صراحةً (documents.client_id) وما
        // يُستنتج من القضية. وقراءةُ واحدةٍ منهما تُخفي نصفَ الملفّ.
        if ($selectedClientId !== null) {
            $selectedClientId > 0
                ? $query->where(fn ($q) => $q->where('documents.client_id', $selectedClientId)
                    ->orWhere(fn ($qq) => $qq->whereNull('documents.client_id')
                        ->whereHas('case', fn ($c) => $c->where('client_id', $selectedClientId))))
                : $query->where(fn ($q) => $q->whereNull('documents.client_id')
                    ->where(fn ($qq) => $qq->whereNull('case_id')
                        ->orWhereHas('case', fn ($c) => $c->whereNull('client_id'))));
        }

        // فلترة بمجلد القضية. «0» تعني «عام» — ما لا مجلد له — وهي قيمة
        // مقصودة لا غياباً، فتُقرأ بـhas لا بـfilled.
        if ($request->has('folder_id') && $request->input('folder_id') !== '') {
            $folderId = (int) $request->input('folder_id');
            $folderId > 0
                ? $query->where('case_folder_id', $folderId)
                : $query->whereNull('case_folder_id');
        }

        if ($request->filled('access_level')) {
            $query->where('access_level', $request->access_level);
        }

        if ($request->filled('file_type')) {
            $query->where('file_type', $request->file_type);
        }

        // فلترة بنوع المستند — في قاعدة البيانات لا في المتصفّح، فتصحّ
        // مع الترقيم مهما كثرت المستندات. و«غير محدد» تعني ما لا نوع له.
        if ($request->filled('doc_type')) {
            $docType = $request->input('doc_type');

            if ($docType === '__untyped__') {
                $query->where(fn ($q) => $q->whereNull('doc_type')->orWhere('doc_type', ''));
            } else {
                $query->where('doc_type', $docType);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('doc_type', 'like', "%{$search}%")
                    ->orWhere('file_type', 'like', "%{$search}%");
            });
        }

        // §4: ترتيب — كلُّ عمودٍ في الجدول يُرتَّب بالنقر على ترويسته
        $sortMap = [
            'created' => 'created_at',
            'name' => 'title',
            'type' => 'file_type',
            'size' => 'file_size',
            'access' => 'access_level',
        ];

        // «القضية» و«الرافع» يعيشان في جدولين آخرين: يُبلَغ إليهما
        // باستعلامٍ مرتبطٍ داخل ORDER BY لا بضمِّ الجدولين — الضمُّ
        // يجعل title وcreated_at وid ملتبسةً فتسقط فلاتر الصفحة.
        $sortSub = [
            'case' => fn () => LegalCase::select('title')
                ->whereColumn('cases.id', 'documents.case_id')->limit(1),
            'uploader' => fn () => \App\Models\User::select('name')
                ->whereColumn('users.id', 'documents.uploaded_by')->limit(1),
        ];

        $sort = (string) $request->get('sort', 'created');
        $sort = (isset($sortMap[$sort]) || isset($sortSub[$sort])) ? $sort : 'created';
        $dir = strtolower($request->get('dir', in_array($sort, ['name', 'case', 'uploader'], true) ? 'asc' : 'desc')) === 'asc' ? 'asc' : 'desc';

        $documents = $query
            ->orderBy(isset($sortSub[$sort]) ? $sortSub[$sort]() : $sortMap[$sort], $dir)
            ->orderBy('documents.id', 'desc')->paginate(15)->withQueryString();

        $doneCount = Document::visibleTo($user)->whereHas('case', fn ($q) => $q->whereIn('status', \App\Models\LegalCase::DONE_STATUSES))->count();
        $cases = LegalCase::with('client')->orderBy('office_case_number')->get();
        $selectedCaseId = (int) $request->query('case_id', 0);

        // الأنواع النشطة للقائمة، وعدد بلا نوع ليُعرض خيار «غير محدد»
        // أشخاصُ المكتب لخانة النسبة في نموذج الرفع
        $formClients = \App\Models\Client::orderBy('name')->get(['id', 'name']);

        $documentTypes = \App\Models\DocumentType::active()->pluck('name');
        $untypedCount = Document::visibleTo($user)->where(fn ($q) => $q->whereNull('doc_type')->orWhere('doc_type', ''))->count();

        $selectedFolderId = $request->has('folder_id') && $request->input('folder_id') !== ''
            ? (int) $request->input('folder_id')
            : null;

        // المجلدُ المفتوح — ويُتحقَّق أنّه من القضية المختارة: معرّفُ
        // مجلدٍ من قضيةٍ أخرى في العنوان يُهمَل بصمت، لا يُعرض شريطُ
        // موضعٍ لقضيةٍ غير التي في الفلتر
        $currentFolder = ($selectedFolderId ?? 0) > 0 && $selectedCaseId > 0
            ? \App\Models\CaseFolder::where('id', $selectedFolderId)
                ->where('case_id', $selectedCaseId)
                ->first()
            : null;

        if (($selectedFolderId ?? 0) > 0 && !$currentFolder) {
            $selectedFolderId = null;
        }

        // مستوىً واحدٌ يُعرض لا الشجرةُ كلُّها: في الجذر مجلداتُ الجذر،
        // وداخل مجلدٍ أبناؤه وحدهم — فالمكانُ الواحد لا يعرض إلا ما
        // فيه، كما في أيّ مستكشف ملفات. و`withCount` يمنع استعلاماً
        // لكل مجلد عند عرض عدده.
        $folders = $selectedCaseId > 0
            ? \App\Models\CaseFolder::where('case_id', $selectedCaseId)
                ->where('parent_id', $currentFolder?->id)
                ->withCount(['documents' => $visible, 'children'])
                ->orderBy('sort')->orderBy('name')->get()
            : collect();

        // سلسلةُ الموضع من الجذر إلى المجلد المفتوح — شريطُ «أين أنا»
        $breadcrumb = $currentFolder?->breadcrumb() ?? [];

        // عدد ما لا مجلد له داخل القضية — ليُعرض بجانب «عام»
        $unfiledCount = $selectedCaseId > 0
            ? Document::visibleTo($user)->where('case_id', $selectedCaseId)->whereNull('case_folder_id')->count()
            : 0;

        // ═══ ما يُعرض في كلّ طبقة ═══
        //
        // الجذر: «مستندات (فلان)» لكلّ موكّلٍ له مستند، وكومةٌ واحدة
        // لما لا موكّلَ له. داخلَ موكّل: قضاياه. داخلَ قضية: مجلداتها
        // كما كانت. فلا شاشةَ تعرض كومةً بلا نسب.
        $clientFolders = collect();
        $clientCases = collect();
        $folderSearch = trim((string) $request->input('folder_search', ''));
        $selectedClient = null;
        $unassignedCount = 0;

        // «كل المستندات» بابٌ صريح: من يبحث عن ورقةٍ لا يعرف صاحبَها
        // يريد الكومةَ كلَّها أمامه، لا شجرةً يتنقّل فيها. وهو أيضاً
        // مخرجُ الرجوع من داخل ملفّ شخص.
        $showAll = $request->boolean('all');

        if ($selectedCaseId === 0 && $selectedClientId === null && !$showAll) {
            // استعلامٌ واحدٌ يجمع العددَ لكلّ موكّل — لا واحدٌ لكلّ صفّ.
            //
            // والصاحبُ محسوبٌ بـCOALESCE: ما نُسب صراحةً أوّلاً، وإلا
            // موكّلُ القضية. والضمُّ يساريٌّ لأنّ مستنداً بلا قضيةٍ قد
            // يكون له صاحبٌ مكتوب — والضمُّ الداخليُّ كان يُسقطه.
            $owner = 'COALESCE(documents.client_id, cases.client_id)';

            $counts = Document::query()
                ->visibleTo($user)
                ->leftJoin('cases', 'documents.case_id', '=', 'cases.id')
                ->whereRaw($owner . ' is not null')
                ->selectRaw($owner . ' as client_id, COUNT(*) as total')
                ->groupBy(\Illuminate\Support\Facades\DB::raw($owner))
                ->pluck('total', 'client_id');

            // بحثٌ في الأسماء: مكتبٌ بمئتي موكّلٍ لا يُقلَّب بالعين
            $clientFolders = \App\Models\Client::whereIn('id', $counts->keys())
                ->when($folderSearch !== '', fn ($q) => $q->where('name', 'like', '%' . $folderSearch . '%'))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($c) => (object) [
                    'id' => $c->id,
                    'name' => 'مستندات (' . $c->name . ')',
                    'count' => (int) ($counts[$c->id] ?? 0),
                ]);

            // «غير منسوبة»: ما لا قضيةَ له أو قضيتُه بلا موكّل — تبقى
            // كما هي بطلبٍ صريح، لكنّها تُجمع في مكانٍ واحدٍ يُعرف
            $unassignedCount = Document::visibleTo($user)->whereNull('client_id')
                ->where(fn ($q) => $q->whereNull('case_id')
                    ->orWhereHas('case', fn ($c) => $c->whereNull('client_id')))
                ->count();
        }

        if (($selectedClientId ?? 0) > 0 && $selectedCaseId === 0) {
            $selectedClient = \App\Models\Client::find($selectedClientId);

            $clientCases = LegalCase::where('client_id', $selectedClientId)
                ->withCount(['documents' => $visible])
                ->orderByDesc('id')
                ->get();
        }

        return view('documents.index', compact(
            'documents', 'cases', 'selectedCaseId', 'documentTypes', 'untypedCount',
            'done', 'doneCount', 'folders', 'selectedFolderId', 'unfiledCount',
            'currentFolder', 'breadcrumb',
            'clientFolders', 'clientCases', 'selectedClient', 'selectedClientId', 'unassignedCount',
            'showAll', 'folderSearch', 'formClients'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        // حدّ الباقة يُفرَض هنا في الخادم — إخفاء زرّ ليس منعاً.
        // ولا يُحذف شيء عند التجاوز: المنع على الإضافة وحدها.
        if (\App\Support\PlanLimits::reached('documents')) {
            return redirect()->back()->withInput()
                ->with('limit_reached', 'documents')
                ->withErrors(['limit' => \App\Support\PlanLimits::message('documents')]);
        }

        $validated = $request->validate([
            'case_id'        => 'nullable|exists:cases,id',
            // نسبةٌ لشخصٍ بلا قضية: وكالةٌ قبل فتح الملفّ، هويةٌ، عقدٌ
            // لموكّلٍ لم يخاصم أحداً بعد. اختياريّةٌ تماماً — و«بلا
            // نسبة» خيارٌ صريحٌ لا نسيان.
            'client_id'      => 'nullable|exists:clients,id',
            'case_folder_id' => 'nullable|integer|exists:case_folders,id',
            'title'          => 'required|string|max:255',
            'file'           => 'required|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png|max:' . (self::MAX_SIZE / 1024),
            'access_level'   => 'required|in:all,team,private',
            'client_visible' => 'nullable|boolean',
            // اسمُ نوعٍ لا وسمٌ برمجي — الخانة حرّة الكتابة الآن
            'doc_type'       => ['nullable', 'string', 'max:80', 'regex:/^[\p{L}\p{M}\p{N}\s\.\-_\(\)\/]+$/u'],
        ]);

        // المجلد يجب أن يتبع نفس القضية — لا قبول لمجلد من قضية أخرى
        $folderId = null;
        if (!empty($validated['case_folder_id']) && !empty($validated['case_id'])) {
            $folderId = \App\Models\CaseFolder::where('id', $validated['case_folder_id'])
                ->where('case_id', $validated['case_id'])
                ->value('id');
        }

        if ($request->filled('case_id')) {
            $case = LegalCase::find($request->input('case_id'));
        }

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        if (!in_array($extension, self::ALLOWED_TYPES)) {
            return back()->withInput()->withErrors(['file' => __('app.document_type_not_allowed')]);
        }

        // الملف لا يُكتب إلا داخل القفل: العدّ والمساحة يُفحصان معاً
        // ذرّياً، فلا يمرّ رفعان متزامنان وكلاهما على آخر جيجابايت.
        try {
            $document = \App\Support\PlanLimits::guard('documents', function () use ($request, $validated, $folderId, $file, $extension) {
                $path = $file->store('documents', 'private');

                // Smart Documents: استنتاج نوع المستند وتاريخه من اسم الملف
                $inferred = \App\Services\DocumentSmartService::inferFromFilename($file->getClientOriginalName());
                // نوعٌ يكتبه الموظّف بيده يُحفظ في قائمة المكتب فيراه من بعده
                $docType = $request->filled('doc_type')
                    ? \App\Models\DocumentType::remember($request->input('doc_type'))
                    : $inferred['type'];
                $docDate = $request->filled('doc_date') ? $request->input('doc_date') : $inferred['date'];

                return Document::create([
                    'case_id'      => $validated['case_id'] ?? null,
                    'client_id'    => $validated['client_id'] ?? null,
                    'case_folder_id' => $folderId,
                    'uploaded_by'  => auth()->id(),
                    'title'        => $validated['title'],
                    'doc_type'     => $docType,
                    'doc_date'     => $docDate,
                    'file_path'    => $path,
                    'file_type'    => $extension,
                    'file_size'    => $file->getSize(),
                    'access_level' => $validated['access_level'],
                    // لا يُعرض للعميل إلا بقرار صريح ممّن يملكه: الخاصُّ لا يُعرض مهما
                    // كان، و«الفريق» لا يُتيحه الموظّف — كان الرفعُ يقبله منه والزرُّ يمنعه
                    'client_visible' => $request->boolean('client_visible')
                        && Document::canBeSharedBy(auth()->user(), $validated['access_level']),
                ]);
            }, (int) $file->getSize());
        } catch (\App\Support\LimitReached $e) {
            return redirect()->back()->withInput()
                ->with('limit_reached', $e->resource)
                ->withErrors(['limit' => \App\Support\PlanLimits::message($e->resource)]);
        }

        $this->logAudit(
            AuditLog::ACTION_CREATE,
            Document::class,
            $document->id,
            null,
            $document->toArray()
        );

        return redirect()->route('documents.index')
            ->with('success', __('app.document_uploaded'));
    }

    /**
     * تبديل ظهور المستند للعميل.
     *
     * لا يُسمح به لمستند خاص إطلاقاً: الخصوصية قرار سابق على المشاركة.
     * والصلاحية تُفحص هنا كما تُفحص في الحذف — لا يكفي إخفاء الزر.
     */
    public function toggleClientVisibility(Document $document): RedirectResponse
    {
        $user = auth()->user();

        if ($document->access_level === Document::ACCESS_PRIVATE) {
            return back()->with('error', 'المستند الخاص لا يمكن مشاركته مع العميل. غيّر مستوى الوصول أولاً.');
        }

        // القاعدةُ نفسُها التي يسألها الرفعُ والزرّ (Document::canBeSharedBy)
        if (!Document::canBeSharedBy($user, $document->access_level)) {
            abort(403);
        }

        $was = (bool) $document->client_visible;
        $document->update(['client_visible' => !$was]);

        // القيمتان مصفوفتين كما يطلب logAudit: كان نصّاً في موضع
        // ‎?array $oldValues‎ وبلا خامس، فيسقط الطلبُ بـArgumentCountError
        // (500) بعد أن قُلب العلَمُ فعلاً — فلم يُشارَك مستندٌ قطّ من الواجهة
        $this->logAudit(
            AuditLog::ACTION_UPDATE,
            Document::class,
            $document->id,
            ['client_visible' => $was, 'title' => $document->title, 'case_id' => $document->case_id],
            ['client_visible' => !$was, 'title' => $document->title, 'case_id' => $document->case_id]
        );

        // الإشعار عند الإتاحة فقط: إخفاءُ مستندٍ لا يُبشَّر به أحد
        if ($document->client_visible) {
            \App\Services\ClientNotifier::notifyDocument($document);
        }

        return back()->with('success', $document->client_visible
            ? 'صار المستند مرئياً للعميل في بوابته.'
            : 'أُخفي المستند عن بوابة العميل.');
    }

    public function destroy(Document $document): RedirectResponse
    {
        $user = auth()->user();

        if ($document->access_level === 'private' && $document->uploaded_by !== $user->id) {
            abort(403, __('app.unauthorized_access'));
        }

        if ($document->access_level === 'team') {
            $isTeam = $user->isAdmin() || $user->isLawyer() || $user->isStaff();
            if (!$isTeam && $document->uploaded_by !== $user->id) {
                abort(403, __('app.unauthorized_access'));
            }
        }

        Storage::disk('private')->delete($document->file_path);

        $oldValues = $document->toArray();
        $document->delete();

        $this->logAudit(
            AuditLog::ACTION_DELETE,
            Document::class,
            $document->id,
            $oldValues,
            null
        );

        return redirect()->route('documents.index')
            ->with('success', __('app.document_deleted'));
    }


    /**
     * اسم الملف عند التنزيل أو المعاينة.
     *
     * العنوان يكتبه المستخدم، وعنوان مثل «عقد 2024/2025» شائع في
     * المكاتب. والشرطة المائلة تجعل Symfony يرفض الاسم فيسقط الطلب
     * برسالة عامة — فلا يستطيع أحد تنزيل ذلك المستند أبداً ولا يفهم
     * السبب. نُنظّف الاسم بدل أن نمنع العنوان.
     */
    private function downloadName(Document $document): string
    {
        $title = str_replace(['/', '\\'], '-', (string) $document->title);
        $title = trim(preg_replace('/\s+/u', ' ', $title)) ?: 'document';

        $ext = ltrim((string) $document->file_type, '.');
        $ext = preg_replace('/[^A-Za-z0-9]/', '', $ext);

        return $ext === '' ? $title : $title . '.' . $ext;
    }

    public function download(Document $document): StreamedResponse|RedirectResponse
    {
        $user = auth()->user();

        if ($document->access_level === 'private' && $document->uploaded_by !== $user->id) {
            abort(403, __('app.unauthorized_access'));
        }

        if ($document->access_level === 'team') {
            $isTeam = $user->isAdmin() || $user->isLawyer() || $user->isStaff();
            if (!$isTeam && $document->uploaded_by !== $user->id) {
                abort(403, __('app.unauthorized_access'));
            }
        }

        if (!Storage::disk('private')->exists($document->file_path)) {
            return redirect()->route('documents.index')
                ->with('error', __('app.file_not_found'));
        }

        // العنوانُ لا مسارُ التخزين: «file_path: documents/xyz.pdf» لا يقول
        // للمدير أيَّ ورقةٍ نُزّلت
        $this->logAudit(
            'download',
            Document::class,
            $document->id,
            null,
            ['title' => $document->title]
        );

        return Storage::disk('private')->download(
            $document->file_path,
            $this->downloadName($document)
        );
    }

    public function preview(Document $document): StreamedResponse|\Illuminate\Http\Response
    {
        $user = auth()->user();

        if ($document->access_level === 'private' && $document->uploaded_by !== $user->id) {
            abort(403, __('app.unauthorized_access'));
        }

        if ($document->access_level === 'team') {
            $isTeam = $user->isAdmin() || $user->isLawyer() || $user->isStaff();
            if (!$isTeam && $document->uploaded_by !== $user->id) {
                abort(403, __('app.unauthorized_access'));
            }
        }

        // المعاينةُ تُفتح داخل إطار العارض أو مصغّرةً في بطاقة: التحويلُ إلى
        // صفحة المستندات كان يرسم التطبيقَ كلَّه — الشريطَ الجانبيّ والترويسةَ —
        // داخل النافذة الصغيرة وفوقه «الملف غير موجود». فصفحةٌ صغيرةٌ بـ٤٠٤.
        if (!Storage::disk('private')->exists($document->file_path)) {
            return response()->view('documents.unavailable', ['document' => $document], 404);
        }

        // ═══ كلُّ وصولٍ يُدوَّن، والتكرارُ وحده يُطوى ═══
        //
        // كان ‎?thumb=1‎ يُسقط سطرَ التدقيق، والمسارُ يردّ الملفَّ الأصليَّ كاملاً
        // (و«مصغّرةُ» PDF هي الملفُّ كلُّه): من كتب المعامِلَ بيده قرأ المستندَ
        // أو حفظه ولا أثرَ له في «من فتح ماذا». فلا يقرّر معامِلٌ في الرابط هل
        // يُدوَّن الوصول. والذي شكا منه المكتبُ — صفحةُ مصغّراتٍ تُعاد فتكتب
        // السطورَ نفسَها مرّةً بعد مرّة — يكفيه سطرٌ واحدٌ لكلّ مستخدمٍ ومستندٍ
        // في عشر دقائق. وإن تعذّر المخزنُ المؤقّت دُوِّن السطر: التدقيقُ لا
        // يسقط بعطلٍ في غيره.
        try {
            $first = Cache::add('audit:preview:' . $user->id . ':' . $document->id, 1, now()->addMinutes(10));
        } catch (\Throwable) {
            $first = true;
        }

        if ($first) {
            $this->logAudit(
                'preview',
                Document::class,
                $document->id,
                null,
                ['title' => $document->title]
            );
        }

        return Storage::disk('private')->response(
            $document->file_path,
            $this->downloadName($document)
        );
    }

}
