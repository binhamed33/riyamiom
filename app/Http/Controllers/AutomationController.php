<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Setting;
use App\Models\User;
use App\Services\Automation\AutomationEngine;
use App\Traits\AuditLoggable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * مركز الأتمتة: إدارة قواعد (متى/إذا/نفّذ) — للإدارة أو من يملك
 * صلاحية automations.manage صراحةً.
 */
class AutomationController extends Controller
{
    use AuditLoggable;

    public function index(): View
    {
        $automations = Automation::with('creator')
            ->withCount(['runs as success_runs_count' => fn ($q) => $q->where('status', AutomationRun::STATUS_SUCCESS)])
            ->latest()
            ->get();

        $todayRuns = AutomationRun::whereDate('created_at', today())
            ->where('status', AutomationRun::STATUS_SUCCESS)->count();
        $failedRecently = AutomationRun::where('status', AutomationRun::STATUS_FAILED)
            ->where('created_at', '>=', now()->subDays(7))->count();

        return view('automations.index', [
            'automations' => $automations,
            'engineEnabled' => AutomationEngine::enabled(),
            'todayRuns' => $todayRuns,
            'failedRecently' => $failedRecently,
            'triggers' => AutomationEngine::triggers(),
            'conditionFields' => AutomationEngine::conditionFields(),
            'operators' => AutomationEngine::operators(),
            'actionTypes' => AutomationEngine::actions(),
            'teamUsers' => User::where('role', '!=', 'client')->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateRule($request);
        $data['created_by'] = auth()->id();
        $data['is_active'] = true;

        $automation = Automation::create($data);

        $this->logAudit(AuditLog::ACTION_CREATE, Automation::class, $automation->id, null, $automation->toArray());

        return redirect()->route('automations.index')->with('success', 'أُنشئت القاعدة «' . $automation->name . '» وهي مفعّلة.');
    }

    public function update(Request $request, Automation $automation): RedirectResponse
    {
        $old = $automation->toArray();
        $automation->update($this->validateRule($request));

        $this->logAudit(AuditLog::ACTION_UPDATE, Automation::class, $automation->id, $old, $automation->fresh()->toArray());

        return redirect()->route('automations.index')->with('success', 'حُدّثت القاعدة «' . $automation->name . '».');
    }

    public function toggle(Automation $automation): RedirectResponse
    {
        $automation->update(['is_active' => !$automation->is_active]);

        return back()->with('success', $automation->is_active
            ? 'فُعّلت قاعدة «' . $automation->name . '».'
            : 'عُطّلت قاعدة «' . $automation->name . '» (تبقى محفوظة).');
    }

    public function destroy(Automation $automation): RedirectResponse
    {
        $old = $automation->toArray();
        // سجل التنفيذ التاريخي يبقى (automation_id يبقى كمرجع رقمي).
        $automation->delete();

        $this->logAudit(AuditLog::ACTION_DELETE, Automation::class, $old['id'], $old, null);

        return back()->with('success', 'حُذفت القاعدة «' . $old['name'] . '». سجل تنفيذها التاريخي محفوظ.');
    }

    /** وضع الاختبار: كم موضوعاً سيطابق القاعدة الآن؟ لا يُنفَّذ أي إجراء. */
    public function test(Automation $automation, AutomationEngine $engine): RedirectResponse
    {
        $count = $engine->dryRun($automation);

        return back()->with('success', 'اختبار «' . $automation->name . '»: ' . $count . ' عنصراً يطابق القاعدة الآن (لم يُنفَّذ شيء).');
    }

    public function runs(Request $request): View
    {
        $query = AutomationRun::with(['automation', 'case'])->latest('id');

        if ($request->filled('automation_id')) {
            $query->where('automation_id', $request->automation_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return view('automations.runs', [
            'runs' => $query->paginate(25)->withQueryString(),
            'automations' => Automation::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function seedDefaults(): RedirectResponse
    {
        $created = AutomationEngine::seedDefaults(auth()->id());

        return back()->with('success', $created > 0
            ? "أُضيفت {$created} قواعد جاهزة — راجعها وعدّلها كما تريد."
            : 'القواعد الجاهزة موجودة مسبقاً.');
    }

    /** المفتاح الرئيسي للمحرك (يبقى أيضاً في لوحة المطور). */
    /**
     * تفعيل الكل أو تعطيل الكل.
     *
     * «تعطيل الكل» لا يحذف قاعدة واحدة: يُطفئ is_active ويبقي كل شيء
     * كما هو، فالعودة ضغطةٌ واحدة. وهذا غير إيقاف المحرّك: ذاك يوقف
     * التشغيل كلّه مؤقتاً وحالةُ القواعد تحته لا تتغيّر، وهذا يغيّر
     * حالة القواعد نفسها.
     */
    public function bulkToggle(Request $request): RedirectResponse
    {
        $enable = $request->input('action') === 'enable';

        $ids = Automation::query()->where('is_active', !$enable)->pluck('id');

        if ($ids->isEmpty()) {
            return back()->with('success', $enable
                ? 'كل القواعد مفعّلة أصلاً.'
                : 'كل القواعد معطّلة أصلاً.');
        }

        Automation::whereIn('id', $ids)->update(['is_active' => $enable]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $enable ? 'automations.enable_all' : 'automations.disable_all',
            'model_type' => Automation::class,
            'model_id' => null,
            'old_values' => ['is_active' => !$enable],
            'new_values' => ['is_active' => $enable, 'count' => $ids->count()],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', $enable
            ? 'فُعّلت ' . $ids->count() . ' قاعدة.'
            : 'عُطّلت ' . $ids->count() . ' قاعدة — كلها محفوظة ولم تُحذف.');
    }

    public function toggleEngine(): RedirectResponse
    {
        $on = AutomationEngine::enabled();
        Setting::set('automation_enabled', $on ? '0' : '1', 'automation');

        return back()->with('success', $on
            ? 'أُوقف محرك الأتمتة بالكامل — القواعد محفوظة ولن تعمل حتى إعادة التفعيل.'
            : 'فُعّل محرك الأتمتة — القواعد النشطة ستعمل في الجولة المجدولة القادمة.');
    }

    // ------------------------------------------------------------------

    private function validateRule(Request $request): array
    {
        $triggers = array_keys(AutomationEngine::triggers());
        $fields = array_keys(AutomationEngine::conditionFields());
        $operators = array_keys(AutomationEngine::operators());
        $actionTypes = array_keys(AutomationEngine::actions());

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'trigger' => ['required', 'in:' . implode(',', $triggers)],
            'conditions' => ['nullable', 'array', 'max:10'],
            'conditions.*.field' => ['required_with:conditions.*', 'in:' . implode(',', $fields)],
            'conditions.*.operator' => ['required_with:conditions.*', 'in:' . implode(',', $operators)],
            'conditions.*.value' => ['nullable', 'string', 'max:190'],
            'actions' => ['required', 'array', 'min:1', 'max:' . AutomationEngine::MAX_ACTIONS_PER_RULE],
            'actions.*.type' => ['required', 'in:' . implode(',', $actionTypes)],
            'actions.*.title' => ['nullable', 'string', 'max:190'],
            'actions.*.message' => ['nullable', 'string', 'max:500'],
            'actions.*.priority' => ['nullable', 'in:low,medium,high,urgent'],
            'actions.*.assign' => ['nullable', 'in:case_lawyer,manager'],
            'actions.*.target' => ['nullable', 'in:case_lawyer,manager,both'],
            'actions.*.status' => ['nullable', 'in:active,pending,overdue,closed,won,lost,adjudicated,fees_pending'],
            'actions.*.due_in_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ], [
            'name.required' => 'اسم القاعدة مطلوب',
            'trigger.required' => 'اختر متى تعمل القاعدة',
            'trigger.in' => 'مشغّل غير معروف',
            'actions.required' => 'أضف إجراءً واحداً على الأقل',
            'actions.*.type.in' => 'إجراء غير معروف',
        ]);

        // تجاهل صفوف الشروط الفارغة
        $validated['conditions'] = array_values(array_filter(
            $validated['conditions'] ?? [],
            fn ($c) => !empty($c['field'])
        ));

        return $validated;
    }
}
