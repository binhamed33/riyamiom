<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CaseTemplate;
use App\Traits\AuditLoggable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * إدارة القوالب الذكية — للإدارة أو من يملك صلاحية templates.manage.
 * القوالب خاصة بقاعدة بيانات المكتب (عزل كامل بين المكاتب بالمعمارية).
 */
class CaseTemplateController extends Controller
{
    use AuditLoggable;

    public function index(): View
    {
        return view('case-templates.index', [
            'templates' => CaseTemplate::with('creator')->orderByDesc('is_active')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateTemplate($request);
        $data['created_by'] = auth()->id();
        $data['is_active'] = true;

        $template = CaseTemplate::create($data);

        $this->logAudit(AuditLog::ACTION_CREATE, CaseTemplate::class, $template->id, null, $template->toArray());

        return redirect()->route('case-templates.index')
            ->with('success', 'أُنشئ القالب «' . $template->name . '».');
    }

    public function update(Request $request, CaseTemplate $caseTemplate): RedirectResponse
    {
        $old = $caseTemplate->toArray();
        $caseTemplate->update($this->validateTemplate($request));

        $this->logAudit(AuditLog::ACTION_UPDATE, CaseTemplate::class, $caseTemplate->id, $old, $caseTemplate->fresh()->toArray());

        return redirect()->route('case-templates.index')
            ->with('success', 'حُدّث القالب «' . $caseTemplate->name . '».');
    }

    public function duplicate(CaseTemplate $caseTemplate): RedirectResponse
    {
        $copy = $caseTemplate->replicate(['usage_count']);
        $copy->name = $caseTemplate->name . ' (نسخة)';
        $copy->usage_count = 0;
        $copy->created_by = auth()->id();
        $copy->save();

        return back()->with('success', 'نُسخ القالب — عدّل «' . $copy->name . '» كما تريد.');
    }

    public function toggle(CaseTemplate $caseTemplate): RedirectResponse
    {
        $caseTemplate->update(['is_active' => !$caseTemplate->is_active]);

        return back()->with('success', $caseTemplate->is_active
            ? 'فُعّل القالب «' . $caseTemplate->name . '».'
            : 'عُطّل القالب «' . $caseTemplate->name . '» — لن يظهر عند إنشاء قضية جديدة.');
    }

    public function destroy(CaseTemplate $caseTemplate): RedirectResponse
    {
        // قالب استُخدم في قضايا: يُعطَّل بدل الحذف حفاظاً على السياق التاريخي.
        if ($caseTemplate->usage_count > 0) {
            $caseTemplate->update(['is_active' => false]);

            return back()->with('error', 'هذا القالب استُخدم في ' . $caseTemplate->usage_count . ' قضية — عُطّل بدلاً من حذفه حفاظاً على السجل. القضايا المنشأة منه لا تتأثر.');
        }

        $old = $caseTemplate->toArray();
        $caseTemplate->delete();

        $this->logAudit(AuditLog::ACTION_DELETE, CaseTemplate::class, $old['id'], $old, null);

        return back()->with('success', 'حُذف القالب «' . $old['name'] . '».');
    }

    // ------------------------------------------------------------------

    private function validateTemplate(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:190'],
            'default_status' => ['nullable', 'in:active,pending,overdue,closed,won,lost,adjudicated,fees_pending'],
            'items' => ['nullable', 'array', 'max:30'],
            'items.*.title' => ['nullable', 'string', 'max:190'],
            'items.*.days_offset' => ['nullable', 'integer', 'min:0', 'max:365'],
            'items.*.priority' => ['nullable', 'in:low,medium,high,urgent'],
            'checklist' => ['nullable', 'array', 'max:30'],
            'checklist.*.title' => ['nullable', 'string', 'max:190'],
            'folders' => ['nullable', 'array', 'max:20'],
            'folders.*.name' => ['nullable', 'string', 'max:100'],
            'reminders' => ['nullable', 'array', 'max:20'],
            'reminders.*.title' => ['nullable', 'string', 'max:190'],
            'reminders.*.days_offset' => ['nullable', 'integer', 'min:0', 'max:365'],
            'reminders.*.target' => ['nullable', 'in:lawyer,manager,both'],
        ], [], ['name' => 'اسم القالب']);

        // تنظيف الصفوف الفارغة من كل مجموعة
        $clean = fn (?array $rows, string $key) => array_values(array_filter(
            $rows ?? [],
            fn ($r) => trim($r[$key] ?? '') !== ''
        ));

        $validated['items'] = $clean($validated['items'] ?? [], 'title');
        $validated['checklist'] = $clean($validated['checklist'] ?? [], 'title');
        $validated['folders'] = $clean($validated['folders'] ?? [], 'name');
        $validated['reminders'] = $clean($validated['reminders'] ?? [], 'title');

        if (!$validated['items'] && !$validated['checklist'] && !$validated['folders'] && !$validated['reminders']) {
            throw ValidationException::withMessages([
                'name' => 'أضف عنصراً واحداً على الأقل (مهمة أو بند تحقق أو مجلد أو تذكير).',
            ]);
        }

        return $validated;
    }
}
