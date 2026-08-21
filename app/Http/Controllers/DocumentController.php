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

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('file_type', 'like', "%{$search}%");
            });
        }

        $documents = $query->latest()->paginate(15)->withQueryString();
        $cases = LegalCase::with('client')->orderBy('office_case_number')->get();
        $selectedCaseId = (int) $request->query('case_id', 0);

        return view('documents.index', compact('documents', 'cases', 'selectedCaseId'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'case_id'        => 'nullable|exists:cases,id',
            'case_folder_id' => 'nullable|integer|exists:case_folders,id',
            'title'          => 'required|string|max:255',
            'file'           => 'required|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png|max:' . (self::MAX_SIZE / 1024),
            'access_level'   => 'required|in:all,team,private',
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
        $docType = $request->filled('doc_type') ? $request->input('doc_type') : $inferred['type'];
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

    public function destroy(Document $document): RedirectResponse
    {
        $user = auth()->user();

        if ($document->access_level === 'private' && $document->uploaded_by !== $user->id) {
            abort(403, 'Access denied.');
        }

        if ($document->access_level === 'team') {
            $isTeam = $user->isAdmin() || $user->isLawyer() || $user->isStaff();
            if (!$isTeam && $document->uploaded_by !== $user->id) {
                abort(403, 'Access denied.');
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

    public function download(Document $document): StreamedResponse|RedirectResponse
    {
        $user = auth()->user();

        if ($document->access_level === 'private' && $document->uploaded_by !== $user->id) {
            abort(403, 'Access denied.');
        }

        if ($document->access_level === 'team') {
            $isTeam = $user->isAdmin() || $user->isLawyer() || $user->isStaff();
            if (!$isTeam && $document->uploaded_by !== $user->id) {
                abort(403, 'Access denied.');
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
            $document->title . '.' . $document->file_type
        );
    }

    public function preview(Document $document): StreamedResponse|RedirectResponse
    {
        $user = auth()->user();

        if ($document->access_level === 'private' && $document->uploaded_by !== $user->id) {
            abort(403, 'Access denied.');
        }

        if ($document->access_level === 'team') {
            $isTeam = $user->isAdmin() || $user->isLawyer() || $user->isStaff();
            if (!$isTeam && $document->uploaded_by !== $user->id) {
                abort(403, 'Access denied.');
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
            $document->title . '.' . $document->file_type
        );
    }

}
