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

    public function index(Request $request): View
    {
        // مصفوفة في ?q[]= كانت تُحوَّل إلى نصّ فترمي ErrorException وتُسقط الصفحة
        $q = is_string($raw = $request->input('q')) ? trim($raw) : '';
        $state = $request->get('state');           // active | disabled
        $sort = $request->get('sort', 'name');     // name | most_used | recent

        return view('case-templates.index', [
            'filters' => ['q' => $q, 'state' => $state, 'sort' => $sort],
            'stats' => [
                'active' => CaseTemplate::where('is_active', true)->count(),
                'disabled' => CaseTemplate::where('is_active', false)->count(),
                'most_used' => CaseTemplate::orderByDesc('usage_count')->where('usage_count', '>', 0)->first()?->name,
            ],
            'templates' => CaseTemplate::with('creator')
                ->when($q !== '', fn ($q2) => $q2->where('name', 'like', '%' . $q . '%'))
                ->when($state === 'active', fn ($q2) => $q2->where('is_active', true))
                ->when($state === 'disabled', fn ($q2) => $q2->where('is_active', false))
                ->when($sort === 'most_used', fn ($q2) => $q2->orderByDesc('usage_count'),
                    fn ($q2) => $sort === 'recent' ? $q2->latest('updated_at') : $q2->orderByDesc('is_active')->orderBy('name'))
                ->get(),
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
        \App\Support\Revisions::capture($caseTemplate, auth()->id());
        $old = $caseTemplate->toArray();
        $caseTemplate->update($this->validateTemplate($request));

        $this->logAudit(AuditLog::ACTION_UPDATE, CaseTemplate::class, $caseTemplate->id, $old, $caseTemplate->fresh()->toArray());

        return redirect()->route('case-templates.index')
            ->with('success', 'حُدّث القالب «' . $caseTemplate->name . '».');
    }

    /** استيراد مكتبة مُداوَلة — آمن للتكرار، ولا يمسّ قالباً عدّله المكتب. */
    public function seedDefaults(): \Illuminate\Http\RedirectResponse
    {
        $created = \App\Models\CaseTemplate::seedDefaults(auth()->id());

        return redirect()->route('case-templates.index')->with('success', $created > 0
            ? "استُوردت {$created} قوالب من مكتبة مُداوَلة — عدّلها كما يناسب مكتبك."
            : 'مكتبة مُداوَلة مستوردة من قبل — لم يتغيّر شيء.');
    }

    public function duplicate(CaseTemplate $caseTemplate): RedirectResponse
    {
        $copy = $caseTemplate->replicate(['usage_count']);
        $copy->name = $caseTemplate->name . ' (نسخة)';
        $copy->usage_count = 0;
        $copy->created_by = auth()->id();
        $copy->save();

        $this->logAudit(AuditLog::ACTION_CREATE, CaseTemplate::class, $copy->id, null, $copy->toArray());

        return back()->with('success', 'نُسخ القالب — عدّل «' . $copy->name . '» كما تريد.');
    }

    public function toggle(CaseTemplate $caseTemplate): RedirectResponse
    {
        $caseTemplate->update(['is_active' => !$caseTemplate->is_active]);

        return back()->with('success', $caseTemplate->is_active
            ? 'فُعّل القالب «' . $caseTemplate->name . '».'
            : 'عُطّل القالب «' . $caseTemplate->name . '» — لن يظهر عند إنشاء قضية جديدة.');
    }

    /** §2: مسودة قالب من جملة المدير — للمعاينة في المحرِّر لا للحفظ المباشر. */
    public function aiDraft(Request $request): \Illuminate\Http\JsonResponse
    {
        $wish = (string) $request->validate(['prompt' => 'required|string|min:10|max:500'])['prompt'];

        try {
        \App\Support\AiSettings::interactive();
            $draft = app(\App\Services\Ai\DraftGenerator::class)->templateDraft($wish);

            return response()->json(['ok' => true, 'draft' => $draft]);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            // مسودةٌ مشوّهة من النموذج ليست عطلاً في الخادم: تُسجَّل ويُقال
            // للمدير أعد الصياغة، بدل 500 ورسالة عامة لا تدلّه على شيء
            \Illuminate\Support\Facades\Log::warning('DraftGenerator: مسودة غير صالحة — ' . $e->getMessage());

            return response()->json(['ok' => false, 'error' => 'وصلت المسودة بشكل غير متوقع — أعد الصياغة بجملة أوضح.'], 422);
        }
    }

    public function versions(CaseTemplate $caseTemplate): \Illuminate\View\View
    {
        return view('revisions.index', [
            'subject' => $caseTemplate,
            'title' => 'نسخ قالب «' . $caseTemplate->name . '»',
            'revisions' => \App\Support\Revisions::for($caseTemplate),
            'restoreRoute' => fn ($v) => route('case-templates.versions.restore', [$caseTemplate, $v]),
            'backUrl' => route('case-templates.index'),
        ]);
    }

    public function restoreVersion(CaseTemplate $caseTemplate, int $version): RedirectResponse
    {
        $before = $caseTemplate->toArray();
        $ok = \App\Support\Revisions::restore($caseTemplate, $version, auth()->id());

        if ($ok) {
            $this->logAudit(AuditLog::ACTION_UPDATE, CaseTemplate::class, $caseTemplate->id, $before, $caseTemplate->fresh()->toArray());
        }

        return $ok
            ? redirect()->route('case-templates.index')->with('success', 'استُعيدت النسخة v' . $version . ' من «' . $caseTemplate->fresh()->name . '» — والحالة السابقة محفوظة كنسخة جديدة.')
            : back()->withErrors(['version' => 'النسخة المطلوبة غير موجودة.']);
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
