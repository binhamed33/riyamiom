<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * إدارة أنواع المستندات — لمدير المكتب.
 *
 * قاعدتان تحكمان الحذف:
 * ‏١) نوع تحته مستندات لا يُحذف، لأن حذفه يترك مستندات بنوع لا وجود له.
 * ‏٢) النوع المدمج لا يُحذف بل يُعطَّل — فيختفي من قائمة الرفع ويبقى
 *    ظاهراً على ما يحمله من مستندات قديمة.
 */
class DocumentTypeController extends Controller
{
    public function index(): View
    {
        $types = DocumentType::orderBy('sort')->orderBy('name')->get();

        // عدّ الاستعمال دفعة واحدة — لا استعلام لكل نوع
        $usage = Document::query()
            ->whereNotNull('doc_type')
            ->selectRaw('doc_type, COUNT(*) as total')
            ->groupBy('doc_type')
            ->pluck('total', 'doc_type');

        $untyped = Document::whereNull('doc_type')->orWhere('doc_type', '')->count();

        return view('document-types.index', compact('types', 'usage', 'untyped'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:80|unique:document_types,name',
        ], [], ['name' => 'اسم النوع']);

        DocumentType::create([
            'name' => trim($validated['name']),
            'is_active' => true,
            'sort' => 500,
            'is_builtin' => false,
        ]);

        return back()->with('success', 'أُضيف النوع.');
    }

    public function update(Request $request, DocumentType $documentType): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:80|unique:document_types,name,' . $documentType->id,
        ], [], ['name' => 'اسم النوع']);

        $oldName = $documentType->name;
        $newName = trim($validated['name']);

        $documentType->update(['name' => $newName]);

        // إعادة التسمية تتبعها المستندات، وإلا صارت تحمل نوعاً لا وجود له
        if ($oldName !== $newName) {
            Document::where('doc_type', $oldName)->update(['doc_type' => $newName]);
        }

        return back()->with('success', 'حُدّث النوع.');
    }

    public function toggle(DocumentType $documentType): RedirectResponse
    {
        $documentType->update(['is_active' => !$documentType->is_active]);

        return back()->with('success', $documentType->is_active
            ? 'صار النوع متاحاً عند الرفع.'
            : 'أُخفي النوع من قائمة الرفع — والمستندات التي تحمله تبقى كما هي.');
    }

    public function destroy(DocumentType $documentType): RedirectResponse
    {
        if ($documentType->is_builtin) {
            return back()->with('error', 'النوع المدمج يُعطَّل ولا يُحذف.');
        }

        $used = $documentType->usageCount();

        if ($used > 0) {
            return back()->with('error', "لا يمكن حذف نوع تحته {$used} مستنداً. عطّله بدل حذفه.");
        }

        $documentType->delete();

        return back()->with('success', 'حُذف النوع.');
    }
}
