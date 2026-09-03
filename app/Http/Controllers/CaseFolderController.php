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
            'parent_id' => ['nullable', 'integer'],
        ]);

        // الأبُ من القضية نفسِها وإلا رُفض: مجلدٌ يولد تحت أبٍ من قضيةٍ
        // أخرى يُظهر مستنداتِ قضيةٍ في شجرة غيرها — بابُ تسريبٍ لا خطأُ
        // تنظيم. والتحقّقُ بالقضية لا بمجرّد الوجود.
        $parent = null;

        if (!empty($validated['parent_id'])) {
            $parent = CaseFolder::where('id', (int) $validated['parent_id'])
                ->where('case_id', $case->id)
                ->first();

            if (!$parent) {
                return back()->with('error', 'المجلد الأب لا يخصّ هذه القضية.');
            }

            if ($parent->depth() >= CaseFolder::MAX_DEPTH) {
                return back()->with('error', 'بلغتَ أقصى عمقٍ للتفريع (' . CaseFolder::MAX_DEPTH . ' طبقات).');
            }
        }

        // التكرارُ يُمنع بين الإخوة لا في القضية كلِّها: «2025» تحت
        // «مذكرات» وأخرى تحت «سندات» تنظيمٌ سليمٌ لا لبسَ فيه — واللبسُ
        // كلُّه في اسمين متطابقين جنباً إلى جنب.
        $exists = CaseFolder::where('case_id', $case->id)
            ->where('parent_id', $parent?->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($validated['name'])])
            ->exists();

        if ($exists) {
            return back()->with('error', 'يوجد مجلد بهذا الاسم في نفس الموضع.');
        }

        $folder = CaseFolder::create([
            'case_id' => $case->id,
            'parent_id' => $parent?->id,
            'name' => $validated['name'],
            'sort' => (int) CaseFolder::where('case_id', $case->id)->max('sort') + 1,
        ]);

        // ويُفتح المجلدُ فورَ إنشائه: من أنشأه أنشأه ليضع فيه شيئاً
        // الآن، لا ليبحث عنه في شريطٍ بعد إنشائه
        return redirect()
            ->route('documents.index', ['case_id' => $case->id, 'folder_id' => $folder->id])
            ->with('success', 'أُنشئ المجلد «' . $validated['name'] . '» وفُتح.');
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

        // الحذفُ يمسّ التنظيمَ لا المحتوى: المستنداتُ والمجلداتُ
        // الفرعية ترتقي إلى أبي المحذوف — فمن حذف «مسوّدات» من داخل
        // «مذكرات» وجد ما كان فيها في «مذكرات» نفسِها، لا مبعثراً في
        // «عام» بعيداً عن موضعه.
        $moved = Document::where('case_folder_id', $folder->id)
            ->update(['case_folder_id' => $folder->parent_id]);

        CaseFolder::where('parent_id', $folder->id)
            ->update(['parent_id' => $folder->parent_id]);

        $folder->delete();

        $destination = $folder->parent_id ? 'إلى المجلد الأعلى' : 'إلى «عام»';

        return back()->with('success', 'حُذف المجلد' . ($moved > 0 ? " ونُقل {$moved} مستنداً {$destination}." : '.'));
    }

    /**
     * نقل مستند إلى مجلد آخر — أو إلى قضية أخرى ومجلد فيها.
     * المجلد يجب أن يكون في القضية التي سيصير إليها المستند، وإلا
     * لَظهر مستند في قضية لا تملك مجلده.
     */
    public function moveDocument(Request $request, Document $document): RedirectResponse
    {
        $this->authorizeManage();

        // authorizeManage تسأل عن الدور لا عن الورقة: مستندٌ «خاصّ»
        // لغيرِ رافعه لا يُنقل ولا يُردّ عنوانُه في رسالة النجاح
        abort_if(
            $document->access_level === 'private' && $document->uploaded_by !== auth()->id(),
            403,
        );

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
