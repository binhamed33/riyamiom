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

    public function index(Request $request): View
    {
        // §29: بحث وتصفية وترتيب من الخادم — الرابط يحمل الحالة فيُشارك
        // مصفوفة في ?q[]= كانت تُحوَّل إلى نصّ فترمي ErrorException وتُسقط الصفحة
        $q = is_string($raw = $request->input('q')) ? trim($raw) : '';
        $state = $request->get('state');           // active | disabled
        $sort = $request->get('sort', 'recent');   // recent | most_used | name

        $automations = Automation::with('creator')
            ->withCount(['runs as success_runs_count' => fn ($q2) => $q2->where('status', AutomationRun::STATUS_SUCCESS)])
            ->when($q !== '', fn ($q2) => $q2->where('name', 'like', '%' . $q . '%'))
            ->when($state === 'active', fn ($q2) => $q2->where('is_active', true))
            ->when($state === 'disabled', fn ($q2) => $q2->where('is_active', false))
            ->when($sort === 'most_used', fn ($q2) => $q2->orderByDesc('runs_count'),
                fn ($q2) => $sort === 'name' ? $q2->orderBy('name') : $q2->latest())
            ->get();

        $todayRuns = AutomationRun::whereDate('created_at', today())
            ->where('status', AutomationRun::STATUS_SUCCESS)->count();
        $todayFailed = AutomationRun::whereDate('created_at', today())
            ->where('status', AutomationRun::STATUS_FAILED)->count();
        $failedRecently = AutomationRun::where('status', AutomationRun::STATUS_FAILED)
            ->where('created_at', '>=', now()->subDays(7))->count();

        return view('automations.index', [
            'filters' => ['q' => $q, 'state' => $state, 'sort' => $sort],
            'stats' => [
                'active' => Automation::where('is_active', true)->count(),
                'disabled' => Automation::where('is_active', false)->count(),
                'today_ok' => $todayRuns,
                'today_failed' => $todayFailed,
                'most_used' => Automation::orderByDesc('runs_count')->where('runs_count', '>', 0)->first()?->name,
            ],
            'suggestions' => \App\Support\AutomationAdvisor::suggestions(),
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
        \App\Support\Revisions::capture($automation, auth()->id());
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

    /** نسخة للتجربة: تُنشأ معطَّلة، فلا تعمل قاعدتان متطابقتان معاً بغتة. */
    public function duplicate(Automation $automation): RedirectResponse
    {
        $copy = $automation->replicate(['runs_count', 'last_run_at']);
        $copy->name = $automation->name . ' (نسخة)';
        $copy->is_active = false;
        $copy->created_by = auth()->id();
        $copy->runs_count = 0;
        $copy->save();

        $this->logAudit(AuditLog::ACTION_CREATE, Automation::class, $copy->id, null, $copy->toArray());

        return back()->with('success', 'نُسخت القاعدة باسم «' . $copy->name . '» معطَّلةً — عدّلها ثم فعّلها.');
    }

    public function versions(Automation $automation): View
    {
        return view('revisions.index', [
            'subject' => $automation,
            'title' => 'نسخ قاعدة «' . $automation->name . '»',
            'revisions' => \App\Support\Revisions::for($automation),
            'restoreRoute' => fn ($v) => route('automations.versions.restore', [$automation, $v]),
            'backUrl' => route('automations.index'),
        ]);
    }

    public function restoreVersion(Automation $automation, int $version): RedirectResponse
    {
        // الاستعادة تعيد كتابة الصفّ كاملاً — أخطر من تعديل حقل، فلا
        // تمرّ خارج سجلّ التدقيق كما كان
        $before = $automation->toArray();
        $ok = \App\Support\Revisions::restore($automation, $version, auth()->id());

        if ($ok) {
            $this->logAudit(AuditLog::ACTION_UPDATE, Automation::class, $automation->id, $before, $automation->fresh()->toArray());
        }

        return $ok
            ? redirect()->route('automations.index')->with('success', 'استُعيدت النسخة v' . $version . ' من «' . $automation->fresh()->name . '» — والحالة السابقة محفوظة كنسخة جديدة.')
            : back()->withErrors(['version' => 'النسخة المطلوبة غير موجودة.']);
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

    /** §11: مسودة قاعدة من جملة المدير — تُعبَّأ بها الاستمارة، ولا تُحفظ هنا. */
    public function aiDraft(Request $request): \Illuminate\Http\JsonResponse
    {
        $wish = (string) $request->validate(['prompt' => 'required|string|min:10|max:500'])['prompt'];

        try {
            $draft = app(\App\Services\Ai\DraftGenerator::class)->automationDraft($wish);

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

    /** §12: تفعيل اقتراح — ينشئ قاعدته الجاهزة بقرار المدير الصريح. */
    public function acceptSuggestion(Request $request): RedirectResponse
    {
        $key = (string) $request->validate(['key' => 'required|string|max:60'])['key'];
        $name = \App\Support\AutomationAdvisor::accept($key, auth()->id());

        return $name
            ? back()->with('success', 'فُعّل الاقتراح: أُنشئت قاعدة «' . $name . '» — تجدها في القائمة وتستطيع تعديلها.')
            : back()->withErrors(['suggestion' => 'الاقتراح لم يعد متاحاً.']);
    }

    public function dismissSuggestion(Request $request): RedirectResponse
    {
        $key = (string) $request->validate(['key' => 'required|string|max:60'])['key'];
        \App\Support\AutomationAdvisor::dismiss($key);

        return back()->with('success', 'أُخفي الاقتراح ولن يظهر مجدداً.');
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
            'actions.*.assign' => ['nullable', 'in:case_lawyer,manager,task_assignee'],
            'actions.*.target' => ['nullable', 'in:case_lawyer,manager,task_assignee,both'],
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
