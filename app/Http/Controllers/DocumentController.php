<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\LegalCase;
use App\Traits\AuditLoggable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $query = Document::with(['case', 'uploader']);
        $user = auth()->user();

        $query->where(function ($q) use ($user) {
            $q->where(function ($qq) {
                $qq->where('access_level', 'all')->orWhereNull('access_level');
            });
            if ($user->isAdmin() || $user->isDeveloper() || $user->isLawyer() || $user->isStaff()) {
                $q->orWhere('access_level', 'team');
            }
            $q->orWhere(function ($qq) use ($user) {
                $qq->where('access_level', 'private')->where('uploaded_by', $user->id);
            });
        });

        if ($request->filled('case_id')) {
            $query->where('case_id', $request->case_id);
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

        $documents = $query->latest()->paginate(15)->withQueryString();
        $cases = LegalCase::with('client')->orderBy('office_case_number')->get();
        $selectedCaseId = (int) $request->query('case_id', 0);

        // الأنواع النشطة للقائمة، وعدد بلا نوع ليُعرض خيار «غير محدد»
        $documentTypes = \App\Models\DocumentType::active()->pluck('name');
        $untypedCount = Document::where(fn ($q) => $q->whereNull('doc_type')->orWhere('doc_type', ''))->count();

        return view('documents.index', compact(
            'documents', 'cases', 'selectedCaseId', 'documentTypes', 'untypedCount'
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
            return back()->withErrors(['file' => 'File type not allowed.']);
        }

        $path = $file->store('documents', 'private');

        // Smart Documents: استنتاج نوع المستند وتاريخه من اسم الملف
        $inferred = \App\Services\DocumentSmartService::inferFromFilename($file->getClientOriginalName());
        // نوعٌ يكتبه الموظّف بيده يُحفظ في قائمة المكتب فيراه من بعده
        $docType = $request->filled('doc_type')
            ? \App\Models\DocumentType::remember($request->input('doc_type'))
            : $inferred['type'];
        $docDate = $request->filled('doc_date') ? $request->input('doc_date') : $inferred['date'];

        $document = Document::create([
            'case_id'      => $validated['case_id'] ?? null,
            'case_folder_id' => $folderId,
            'uploaded_by'  => auth()->id(),
            'title'        => $validated['title'],
            'doc_type'     => $docType,
            'doc_date'     => $docDate,
            'file_path'    => $path,
            'file_type'    => $extension,
            'file_size'    => $file->getSize(),
            'access_level' => $validated['access_level'],
            // لا يُعرض للعميل إلا بقرار صريح، والخاص لا يُعرض مهما كان
            'client_visible' => $request->boolean('client_visible')
                && $validated['access_level'] !== Document::ACCESS_PRIVATE,
        ]);

        $this->logAudit(
            AuditLog::ACTION_CREATE,
            Document::class,
            $document->id,
            null,
            $document->toArray()
        );

        return redirect()->route('documents.index')
            ->with('success', 'Document uploaded successfully.');
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

        if ($document->access_level === 'team' && !in_array($user->role, ['developer', 'admin', 'lawyer'], true)) {
            abort(403);
        }

        $document->update(['client_visible' => !$document->client_visible]);

        $this->logAudit(
            AuditLog::ACTION_UPDATE,
            Document::class,
            $document->id,
            'تغيير ظهور المستند للعميل: ' . ($document->client_visible ? 'مرئي' : 'مخفي')
        );

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
            ->with('success', 'Document deleted successfully.');
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

        $this->logAudit(
            'download',
            Document::class,
            $document->id,
            null,
            ['file_path' => $document->file_path]
        );

        return Storage::disk('private')->download(
            $document->file_path,
            $this->downloadName($document)
        );
    }

    public function preview(Document $document): StreamedResponse|RedirectResponse
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

        $this->logAudit(
            'preview',
            Document::class,
            $document->id,
            null,
            ['file_path' => $document->file_path]
        );

        return Storage::disk('private')->response(
            $document->file_path,
            $this->downloadName($document)
        );
    }

}
