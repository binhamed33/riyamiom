<?php

namespace App\Http\Controllers;

use App\Models\CaseFolder;
use App\Models\Document;
use App\Models\LegalCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * مجلدات القضية: المحامي يبني تنظيمه بنفسه — «مذكرات»، «سندات»،
 * «مراسلات» — ثم ينقل المستندات بينها. لا حذف لمستند هنا أبداً:
 * حذف المجلد يعيد مستنداته إلى «عام» ولا يمسّها.
 */
class CaseFolderController extends Controller
{
    public function store(Request $request, LegalCase $case): RedirectResponse
    {
        $this->authorizeManage();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', 'regex:/^[\p{L}\p{M}\p{N}\s\.\-_\(\)\/]+$/u'],
        ]);

        $exists = CaseFolder::where('case_id', $case->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($validated['name'])])
            ->exists();

        if ($exists) {
            return back()->with('error', 'يوجد مجلد بهذا الاسم في القضية.');
        }

        CaseFolder::create([
            'case_id' => $case->id,
            'name' => $validated['name'],
            'sort' => (int) CaseFolder::where('case_id', $case->id)->max('sort') + 1,
        ]);

        return back()->with('success', 'أُنشئ المجلد «' . $validated['name'] . '».');
    }

    public function update(Request $request, CaseFolder $folder): RedirectResponse
    {
        $this->authorizeManage();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', 'regex:/^[\p{L}\p{M}\p{N}\s\.\-_\(\)\/]+$/u'],
        ]);

        $folder->update(['name' => $validated['name']]);

        return back()->with('success', 'أُعيدت تسمية المجلد.');
    }

    public function destroy(CaseFolder $folder): RedirectResponse
    {
        $this->authorizeManage();

        // المستندات تعود إلى «عام» — الحذف يمسّ التنظيم لا المحتوى
        $moved = Document::where('case_folder_id', $folder->id)->update(['case_folder_id' => null]);
        $folder->delete();

        return back()->with('success', 'حُذف المجلد' . ($moved > 0 ? " ونُقل {$moved} مستنداً إلى «عام»." : '.'));
    }

    /**
     * نقل مستند إلى مجلد آخر — أو إلى قضية أخرى ومجلد فيها.
     * المجلد يجب أن يكون في القضية التي سيصير إليها المستند، وإلا
     * لَظهر مستند في قضية لا تملك مجلده.
     */
    public function moveDocument(Request $request, Document $document): RedirectResponse
    {
        $this->authorizeManage();

        $validated = $request->validate([
            'case_id' => 'nullable|integer|exists:cases,id',
            'case_folder_id' => 'nullable|integer|exists:case_folders,id',
        ]);

        $caseId = $validated['case_id'] ?? $document->case_id;
        $folderId = $validated['case_folder_id'] ?? null;

        if ($folderId) {
            $folder = CaseFolder::find($folderId);
            if (!$folder || (int) $folder->case_id !== (int) $caseId) {
                return back()->with('error', 'المجلد لا يخصّ هذه القضية.');
            }
        }

        $document->update([
            'case_id' => $caseId,
            'case_folder_id' => $folderId,
        ]);

        return back()->with('success', 'نُقل المستند «' . $document->title . '».');
    }

    /** الفريق ينظّم، والعميل لا يصل إلى هذه المسارات إطلاقاً. */
    private function authorizeManage(): void
    {
        $user = auth()->user();

        abort_unless(
            $user && ($user->isAdmin() || $user->isDeveloper() || $user->isLawyer() || $user->isStaff()),
            403,
            'لا تملك صلاحية تنظيم مستندات القضايا.'
        );
    }
}
