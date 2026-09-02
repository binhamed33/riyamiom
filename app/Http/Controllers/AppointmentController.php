<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\LegalCase;
use App\Models\User;
use App\Services\AppointmentNotifier;
use App\Support\AppointmentSlots;
use App\Traits\AuditLoggable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * حجزُ المواعيد وترتيبُها.
 *
 * ═══ الحارسُ عند الحفظ لا عند العرض ═══
 *
 * شاشةُ الحجز تعرض الفُسَحَ الشاغرة، لكنّ شاشتين مفتوحتين تريان
 * الفُسحةَ نفسَها. فالتعارضُ يُفحص هنا قبل الكتابة — وإلا وقف موكّلان
 * على الباب في التاسعة.
 */
class AppointmentController extends Controller
{
    use AuditLoggable;

    /** تقويمُ يومٍ أو أسبوع، وقائمةُ القادم. */
    public function index(Request $request): View
    {
        $day = $this->parseDay($request->query('day'));

        $query = Appointment::with(['client', 'user', 'case']);

        // «القادمة» افتراضاً: الشاشةُ تُفتح لتقول ما الذي ينتظر المكتب،
        // لا لتعرض أرشيفاً. واليومُ المحدَّد يقلبها إلى تقويم ذلك اليوم.
        $scope = $request->query('scope', $request->filled('day') ? 'day' : 'upcoming');

        if ($scope === 'day') {
            $query->whereBetween('starts_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()]);
        } elseif ($scope === 'past') {
            $query->where('starts_at', '<', now())->orderByDesc('starts_at');
        } else {
            $query->where('starts_at', '>=', now()->startOfDay());
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', (int) $request->input('client_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $appointments = ($scope === 'past' ? $query : $query->orderBy('starts_at'))
            ->paginate(20)->withQueryString();

        return view('appointments.index', [
            'appointments' => $appointments,
            'day' => $day,
            'scope' => $scope,
            'staff' => $this->staff(),
            'todayCount' => Appointment::whereBetween('starts_at', [now()->startOfDay(), now()->endOfDay()])
                ->where('status', Appointment::STATUS_SCHEDULED)->count(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('appointments.create', [
            'clients' => Client::orderBy('name')->get(['id', 'name']),
            'cases' => LegalCase::with('client:id,name')->orderByDesc('id')->get(['id', 'case_number', 'title', 'client_id']),
            'staff' => $this->staff(),
            'day' => $this->parseDay($request->query('day')),
            'presetClient' => (int) $request->query('client_id', 0),
            'presetCase' => (int) $request->query('case_id', 0),
        ]);
    }

    /** الفُسَحُ الشاغرةُ ليومٍ وموظّف — تُنادى من الشاشة عند تغيّر أيّهما. */
    public function slots(Request $request): JsonResponse
    {
        $day = $this->parseDay($request->query('day'));
        $userId = (int) $request->query('user_id', 0) ?: null;
        $ignore = (int) $request->query('ignore', 0) ?: null;

        $slots = AppointmentSlots::forDay($day, $userId, $ignore);

        return response()->json([
            'workday' => AppointmentSlots::isWorkday($day),
            'day' => $day->toDateString(),
            'slots' => array_map(static fn ($s) => ['time' => $s['time'], 'free' => $s['free']], $slots),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        if (($conflict = $this->conflict($data)) !== null) {
            return back()->withInput()->with('error', $conflict);
        }

        $appointment = Appointment::create($data + [
            'status' => Appointment::STATUS_SCHEDULED,
            'created_by' => auth()->id(),
        ]);

        $this->logAudit('created', Appointment::class, $appointment->id, null, $appointment->toArray());
        AppointmentNotifier::created($appointment);

        return redirect()->route('appointments.index', ['day' => $appointment->starts_at->toDateString()])
            ->with('success', 'حُجز الموعد وأُبلغ به الموكّل.');
    }

    public function edit(Appointment $appointment): View
    {
        return view('appointments.edit', [
            'appointment' => $appointment->load(['client', 'case', 'user']),
            'clients' => Client::orderBy('name')->get(['id', 'name']),
            'cases' => LegalCase::with('client:id,name')->orderByDesc('id')->get(['id', 'case_number', 'title', 'client_id']),
            'staff' => $this->staff(),
        ]);
    }

    public function update(Request $request, Appointment $appointment): RedirectResponse
    {
        $data = $this->validated($request);
        $data['status'] = $request->input('status', $appointment->status);

        if (($conflict = $this->conflict($data, $appointment->id)) !== null) {
            return back()->withInput()->with('error', $conflict);
        }

        $before = $appointment->toArray();
        $wasAt = $appointment->starts_at->copy();
        $wasStatus = $appointment->status;

        $appointment->update($data);
        $this->logAudit('updated', Appointment::class, $appointment->id, $before, $appointment->toArray());

        // ═══ ما الذي يستحقّ رسالةً ═══
        //
        // تغييرُ الوقت يعني موكّلاً يقف في الساعة الخطأ إن لم يُخبَر،
        // والإلغاءُ يعني رحلةً بلا داعٍ. أمّا تصحيحُ ملاحظةٍ داخلية أو
        // تعليمُ الموعد «تمّ» فلا شأنَ للموكّل به — ورسالةٌ عنه ضجيج.
        if (!$appointment->starts_at->equalTo($wasAt) && $appointment->status === Appointment::STATUS_SCHEDULED) {
            AppointmentNotifier::moved($appointment);
        } elseif ($appointment->status === Appointment::STATUS_CANCELLED && $wasStatus !== Appointment::STATUS_CANCELLED) {
            AppointmentNotifier::cancelled($appointment);
        }

        return redirect()->route('appointments.index', ['day' => $appointment->starts_at->toDateString()])
            ->with('success', 'حُدّث الموعد.');
    }

    /** تغييرُ الحالة وحدها من القائمة — بلا فتح شاشة التعديل. */
    public function status(Request $request, Appointment $appointment): RedirectResponse
    {
        $request->validate(['status' => 'required|in:' . implode(',', array_keys(Appointment::STATUSES))]);

        $was = $appointment->status;
        $appointment->update(['status' => $request->input('status')]);
        $this->logAudit('updated', Appointment::class, $appointment->id, ['status' => $was], ['status' => $appointment->status]);

        if ($appointment->status === Appointment::STATUS_CANCELLED && $was !== Appointment::STATUS_CANCELLED) {
            AppointmentNotifier::cancelled($appointment);
        }

        return back()->with('success', 'حُدّثت حالة الموعد.');
    }

    public function destroy(Appointment $appointment): RedirectResponse
    {
        // الحذفُ يُلغي أوّلاً: الموكّلُ يعرف أنّ الموعد لم يعد قائماً،
        // ولا يبقى واقفاً على بابٍ لموعدٍ مُحي من الشاشة وحدها
        if ($appointment->status === Appointment::STATUS_SCHEDULED && $appointment->starts_at->isFuture()) {
            $appointment->update(['status' => Appointment::STATUS_CANCELLED]);
            AppointmentNotifier::cancelled($appointment);
        }

        $this->logAudit('deleted', Appointment::class, $appointment->id, $appointment->toArray(), null);
        $appointment->delete();

        return redirect()->route('appointments.index')->with('success', 'حُذف الموعد.');
    }

    // ------------------------------------------------------------ الداخل

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'case_id' => 'nullable|exists:cases,id',
            'user_id' => 'nullable|exists:users,id',
            'title' => 'required|string|max:190',
            'date' => 'required|date',
            'time' => 'required|date_format:H:i',
            'minutes' => 'nullable|integer|min:5|max:480',
            'location' => 'nullable|string|max:190',
            'notes' => 'nullable|string|max:2000',
        ], [], [
            'client_id' => 'الموكّل',
            'title' => 'موضوع الموعد',
            'date' => 'التاريخ',
            'time' => 'الوقت',
        ]);

        $starts = Carbon::parse($data['date'] . ' ' . $data['time']);

        return [
            'client_id' => $data['client_id'],
            'case_id' => $data['case_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'title' => $data['title'],
            'starts_at' => $starts,
            'minutes' => $data['minutes'] ?? AppointmentSlots::slotMinutes(),
            'location' => $data['location'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    /** رسالةُ تعارضٍ إن وُجد — أو null. */
    private function conflict(array $data, ?int $ignoreId = null): ?string
    {
        if (AppointmentSlots::isFree($data['starts_at'], (int) $data['minutes'], $data['user_id'] ?? null, $ignoreId)) {
            return null;
        }

        return 'هذا الوقت محجوزٌ لدى الموظّف نفسِه — اختر وقتاً آخر أو موظّفاً آخر.';
    }

    private function parseDay(?string $raw): Carbon
    {
        try {
            return $raw ? Carbon::parse($raw)->startOfDay() : now()->startOfDay();
        } catch (\Throwable) {
            return now()->startOfDay();
        }
    }

    private function staff()
    {
        return User::where('is_active', true)
            ->whereIn('role', ['developer', 'admin', 'lawyer', 'staff'])
            ->orderBy('name')->get(['id', 'name', 'role']);
    }
}
