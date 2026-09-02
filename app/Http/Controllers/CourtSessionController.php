<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\LegalCase;
use App\Models\Notification;
use App\Models\Session;
use App\Models\User;
use App\Traits\AuditLoggable;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourtSessionController extends Controller
{
    use AuditLoggable;

    /** سقفُ ورقةٍ واحدة — ويُقال للقارئ صراحةً إن بلغه الجدول. */
    private const PRINT_LIMIT = 300;

    public function index(Request $request): View
    {
        $query = $this->filtered($request);

        // §4: ترتيب بمفاتيح معلومة — والافتراضي كما كان: الأقرب موعداً أولاً
        $sortMap = ['date' => 'date', 'created' => 'created_at', 'status' => 'status', 'location' => 'location'];

        // ═══ أعمدةٌ لا تعيش في جدول الجلسات ═══
        //
        // «المحكمة» و«الموكّل» و«الخصم» تُقرأ من القضية وموكّلها، فلا
        // يعرفها ORDER BY حتى يُبلَغ إليها. والضمُّ (JOIN) كان سيجعل
        // «الحالة» و«المعرّف» و«تاريخ الإنشاء» ملتبسةً بين الجدولين
        // فتسقط فلاتر الصفحة كلُّها بـambiguous column؛ فالوصولُ
        // باستعلامٍ مرتبطٍ داخل ORDER BY وحدَه: لا يمسّ الاستعلامَ
        // الأصليَّ بحرف، ولا يضاعف صفّاً، ولا يكسر عدَّ الترقيم.
        //
        // و«الخصم» ليس منها: LegalCase::$encryptable يضمّه، فهو مخزَّنٌ
        // مشفَّراً بمتّجهٍ عشوائيٍّ لكلّ صفّ — ORDER BY عليه يرتّب
        // نصّاً مشفَّراً، أي ترتيبٌ يختلف عن نفسه بين حفظٍ وحفظ. وهذا
        // ما كان: ترويسةٌ تُنقر فتتحرّك الصفوف، والحركةُ محضُ صدفة.
        $sortSub = [
            'court' => fn () => LegalCase::select('court')
                ->whereColumn('cases.id', 'court_sessions.case_id')->limit(1),
            'client' => fn () => \App\Models\Client::select('clients.name')
                ->join('cases', 'cases.client_id', '=', 'clients.id')
                ->whereColumn('cases.id', 'court_sessions.case_id')->limit(1),
        ];

        $sort = (string) $request->get('sort', 'date');
        $sort = (isset($sortMap[$sort]) || isset($sortSub[$sort])) ? $sort : 'date';
        $dir = strtolower($request->get('dir', $sort === 'date' ? 'asc' : 'desc')) === 'desc' ? 'desc' : 'asc';

        $sessions = $query
            ->orderBy(isset($sortSub[$sort]) ? $sortSub[$sort]() : $sortMap[$sort], $dir)
            ->orderBy('court_sessions.id', 'asc')->paginate(15)->withQueryString();

        $doneCount = Session::whereHas('case', fn ($q) => $q->whereIn('status', \App\Models\LegalCase::DONE_STATUSES))->count();

        return view('sessions.index', [
            'sessions' => $sessions,
            'done' => $request->boolean('done'),
            'doneCount' => $doneCount,
        ] + $this->filterLists());
    }

    /**
     * §30: جدول جلسات قابل للطباعة — نفس الفلاتر، ترويسة المكتب، بلا واجهة.
     * ورقة تُحمل إلى المحكمة: من يطبع «جلسات الأسبوع» يحصل عليها كما صفّاها.
     */
    public function print(Request $request): View
    {
        $sessions = $this->filtered($request)
            ->orderBy('date', 'asc')->orderBy('id', 'asc')
            ->limit(self::PRINT_LIMIT)->get();

        // ‏?status[]=x يصل مصفوفةً، وتحويلها إلى نصّ يرمي ErrorException —
        // القائمة كانت تتسامح معها والطباعة تسقط. النصّ وحده يُقبل هنا.
        $text = static fn (string $key): ?string => is_string($v = $request->input($key)) && $v !== '' ? $v : null;

        return view('sessions.print', [
            'sessions' => $sessions,
            'truncated' => $sessions->count() >= self::PRINT_LIMIT,
            'generatedAt' => now(),
            'filtersSummary' => collect([
                $request->boolean('mine') ? 'جلساتي فقط' : null,
                $text('status') ? 'الحالة: ' . __('app.status_' . $text('status')) : null,
                $text('court') ? 'المحكمة: ' . $text('court') : null,
                $text('date_from') ? 'من ' . $text('date_from') : null,
                $text('date_to') ? 'إلى ' . $text('date_to') : null,
                $text('range') ? (['today' => 'اليوم', 'week' => 'هذا الأسبوع', 'month' => 'هذا الشهر'][$text('range')] ?? null) : null,
            ])->filter()->implode(' • '),
        ]);
    }

    /** استعلام الجلسات بنفس فلاتر الصفحة — تستعمله القائمة والطباعة معاً. */
    private function filtered(Request $request)
    {
        $query = Session::with(['case.client', 'case.lawyer']);

        // سياسة المكتب: كل عضو في الفريق يرى جلسات المكتب كلّها — كما هي
        // صفحة القضية نفسها. ومن أراد جلساته وحده فله «جلساتي» أدناه،
        // فلا يفقد أحد العرض الذي اعتاده.
        if ($request->boolean('mine')) {
            $query->whereHas('case', fn ($q) => $q->where('lawyer_id', auth()->id()));
        }

        // جلسات القضايا المنجزة تُطوى خلف زر «المنجزة» — والجلسة بلا قضية تبقى ظاهرة
        if ($request->boolean('done')) {
            $query->whereHas('case', fn ($q) => $q->whereIn('status', \App\Models\LegalCase::DONE_STATUSES));
        } else {
            $query->where(function ($q) {
                $q->whereNull('case_id')
                    ->orWhereHas('case', fn ($c) => $c->whereNotIn('status', \App\Models\LegalCase::DONE_STATUSES));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('case_id')) {
            $query->where('case_id', $request->case_id);
        }

        if ($request->filled('lawyer_id')) {
            $query->whereHas('case', fn ($q) => $q->where('lawyer_id', $request->lawyer_id));
        }

        if ($request->filled('court')) {
            $query->whereHas('case', fn ($q) => $q->where('court', $request->court));
        }

        if ($request->filled('range')) {
            match ($request->range) {
                'today' => $query->whereDate('date', now()->startOfDay()),
                'week' => $query->whereBetween('date', [now()->startOfWeek(), now()->endOfWeek()]),
                'month' => $query->whereBetween('date', [now()->startOfMonth(), now()->endOfMonth()]),
                default => null,
            };
        }

        if ($request->filled('date_from')) {
            $query->where('date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('date', '<=', $request->date_to);
        }

        return $query;
    }

    /**
     * قوائم التصفية التي تحتاجها صفحة الجلسات.
     *
     * موضعان يعرضان الصفحة نفسها: القائمة و«جلسات اليوم». كانت الثانية
     * تعرضها بلا هذه القوائم، فتسقط الصفحة على متغيّر غير معرّف ويُرمى
     * المستخدم إلى لوحة المتابعة برسالة عامة. القوائم هنا فلا يفترق موضع
     * عن آخر.
     *
     */
    private function filterLists(): array
    {
        return [
            'filterCases' => LegalCase::orderBy('office_case_number')
                ->get(['id', 'office_case_number', 'title']),

            'filterLawyers' => User::where('role', '!=', 'client')
                ->orderBy('name')
                ->get(['id', 'name']),

            'filterCourts' => LegalCase::whereNotNull('court')
                ->where('court', '!=', '')
                ->distinct()
                ->orderBy('court')
                ->pluck('court'),
        ];
    }

    public function create(Request $request): View
    {
        $cases = LegalCase::with('client')->orderBy('office_case_number')->get();
        $selectedCaseId = $request->query('case_id');

        return view('sessions.create', compact('cases', 'selectedCaseId'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'case_id'  => 'required|exists:cases,id',
            'date'     => 'required|date',
            'location' => 'required|string|max:255',
            'status'   => 'required|in:upcoming,completed,postponed,cancelled',
            'notes'    => 'nullable|string',
            'report'   => 'nullable|string',
        ]);

        $case = LegalCase::findOrFail($validated['case_id']);

        $session = Session::create($validated);

        $this->logAudit(
            AuditLog::ACTION_CREATE,
            Session::class,
            $session->id,
            null,
            $session->toArray()
        );

        \App\Services\ClientNotifier::notifyCaseUpdate($case);
        \App\Services\ClientNotifier::notifySession($case, $session);

        $case = LegalCase::find($validated['case_id']);
        if ($case && $case->lawyer_id) {
            \App\Support\Notify::send(
                userId: $case->lawyer_id,
                titleKey: 'app.notif_session_title',
                messageKey: 'app.notif_session_body',
                params: ['case' => $case->title, 'date' => $session->date],
                type: Notification::TYPE_INFO,
                notifiableType: Session::class,
                notifiableId: $session->id,
            );
        }

        return redirect()->route('sessions.show', $session)
            ->with('success', 'Court session created successfully.');
    }

    public function quickStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'case_id'  => 'required|exists:cases,id',
            'date'     => 'required|date',
            'location' => 'nullable|string|max:255',
            'status'   => 'required|in:upcoming,completed,postponed,cancelled',
            'notes'    => 'nullable|string',
            'report'   => 'nullable|string',
        ]);

        $case = LegalCase::findOrFail($validated['case_id']);

        $validated['location'] = $validated['location'] ?? '';

        $session = Session::create($validated);

        $this->logAudit(
            AuditLog::ACTION_CREATE,
            Session::class,
            $session->id,
            null,
            $session->toArray()
        );

        \App\Services\ClientNotifier::notifyCaseUpdate($case);

        if ($case->lawyer_id) {
            \App\Support\Notify::send(
                userId: $case->lawyer_id,
                titleKey: 'app.notif_session_title',
                messageKey: 'app.notif_session_body',
                params: ['case' => $case->title, 'date' => $session->date],
                type: Notification::TYPE_INFO,
                notifiableType: Session::class,
                notifiableId: $session->id,
            );
        }

        return response()->json([
            'success' => true,
            'session' => [
                'id'       => $session->id,
                'case_id'  => $session->case_id,
                'date'     => $session->date?->format('Y-m-d H:i:s'),
                'location' => $session->location,
                'status'   => $session->status,
                'notes'    => $session->notes,
                'report'   => $session->report,
            ],
        ]);
    }

    public function show(Session $session): View
    {
        $this->authorizeSessionAccess($session);

        $session->load('case.client', 'case.lawyer');

        return view('sessions.show', compact('session'));
    }

    public function edit(Session $session): View
    {
        $this->authorizeSessionAccess($session);

        $cases = LegalCase::with('client')->orderBy('office_case_number')->get();

        return view('sessions.edit', compact('session', 'cases'));
    }

    public function update(Request $request, Session $session): RedirectResponse
    {
        $this->authorizeSessionAccess($session);

        $validated = $request->validate([
            'case_id'  => 'required|exists:cases,id',
            'date'     => 'required|date',
            'location' => 'required|string|max:255',
            'status'   => 'required|in:upcoming,completed,postponed,cancelled',
            'notes'    => 'nullable|string',
            'report'   => 'nullable|string',
        ]);

        $oldValues = $session->toArray();
        $session->update($validated);

        $this->logAudit(
            AuditLog::ACTION_UPDATE,
            Session::class,
            $session->id,
            $oldValues,
            $session->toArray()
        );

        $case = LegalCase::find($session->case_id);
        if ($case) {
            \App\Services\ClientNotifier::notifyCaseUpdate($case);
        }

        return redirect()->route('sessions.show', $session)
            ->with('success', 'Court session updated successfully.');
    }

    public function destroy(Session $session): RedirectResponse
    {
        $this->authorizeSessionAccess($session);

        $oldValues = $session->toArray();
        $session->delete();

        $this->logAudit(
            AuditLog::ACTION_DELETE,
            Session::class,
            $session->id,
            $oldValues,
            null
        );

        return redirect()->route('sessions.index')
            ->with('success', 'Court session deleted successfully.');
    }

    private function authorizeSessionAccess(Session $session): void
    {
        // All team members can access any session
    }

    public function today(): View
    {
        $query = Session::with(['case.client', 'case.lawyer']);

        $query->whereDate('date', Carbon::today())
            ->where('status', 'upcoming')
            ->orderBy('date');

        $sessions = $query->paginate(15);

        return view('sessions.index', ['sessions' => $sessions] + $this->filterLists());
    }

}
