<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\SubscriptionService;
use App\Traits\AuditLoggable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DeveloperSubscriptionController extends Controller
{
    use AuditLoggable;

    private function service(): SubscriptionService
    {
        return new SubscriptionService();
    }

    public function index(Request $request): View
    {
        $query = Tenant::with('subscription')->withCount('users');

        $search = trim((string) $request->input('q'));
        $status = $request->input('status', '');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('owner_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $tenants = $query->orderBy('name')->paginate(20)->withQueryString();

        $rows = $tenants->map(function (Tenant $tenant) {
            $subscription = $tenant->subscription;
            $statusInfo = $this->service()->statusForTenant($tenant, $subscription);

            return (object) [
                'tenant' => $tenant,
                'subscription' => $subscription,
                'status' => $statusInfo,
                'user_count' => $tenant->users_count ?? 0,
            ];
        });

        $all = Tenant::with('subscription')->get();
        $stats = [
            'total' => $all->count(),
            'active' => 0,
            'expiring' => 0,
            'blocked' => 0,
            'no_subscription' => 0,
        ];
        foreach ($all as $tenant) {
            $info = $this->service()->statusForTenant($tenant, $tenant->subscription);
            if (!$info['subscription']) {
                $stats['no_subscription']++;
            } elseif (in_array($info['key'], ['active', 'expiring_soon'], true)) {
                $stats['active']++;
                if ($info['key'] === 'expiring_soon') {
                    $stats['expiring']++;
                }
            } else {
                $stats['blocked']++;
            }
        }

        return view('developer.subscriptions.index', compact('rows', 'stats', 'search', 'status'));
    }

    public function create(): View
    {
        $tenants = Tenant::with('subscription')->orderBy('name')->get();
        $durations = Subscription::DURATION_OPTIONS;

        return view('developer.subscriptions.create', compact('tenants', 'durations'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'duration' => 'required|integer|in:1,2,3,6,12',
            'notes' => 'nullable|string|max:255',
        ]);

        $tenant = Tenant::findOrFail($validated['tenant_id']);

        $current = $tenant->subscription;
        if ($current && $current->isActive()) {
            return redirect()->route('developer.subscriptions.index')
                ->with('error', 'هذا العميل لديه اشتراك نشط بالفعل، استخدم تمديد أو تغيير الاشتراك.');
        }

        $start = Carbon::today();
        $end = Subscription::endDateFor($start, (int) $validated['duration']);

        $subscription = Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_duration_months' => (int) $validated['duration'],
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'status' => Subscription::STATUS_ACTIVE,
            'notes' => $validated['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        $this->logAudit(
            AuditLog::ACTION_CREATE,
            Subscription::class,
            $subscription->id,
            null,
            [
                'action' => 'create_subscription',
                'tenant' => $tenant->name,
                'tenant_id' => $tenant->id,
                'duration' => $validated['duration'],
                'start_date' => $subscription->start_date->toDateString(),
                'end_date' => $subscription->end_date->toDateString(),
            ]
        );

        return redirect()->route('developer.subscriptions.index')
            ->with('success', 'تم إنشاء الاشتراك بنجاح لـ ' . $tenant->name . ' حتى ' . $subscription->end_date->toDateString());
    }

    public function extend(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'duration' => 'required|integer|in:1,2,3,6,12',
            'notes' => 'nullable|string|max:255',
        ]);

        $subscription = $tenant->subscription;

        if (!$subscription) {
            return redirect()->route('developer.subscriptions.index')
                ->with('error', 'لا يوجد اشتراك لهذا العميل لتمديده.');
        }

        $old = $subscription->toArray();

        $base = $subscription->end_date->greaterThan(now()) ? $subscription->end_date : Carbon::today();
        $newEnd = Subscription::endDateFor($base->toDateString(), (int) $validated['duration']);

        $subscription->end_date = $newEnd->toDateString();
        $subscription->plan_duration_months += (int) $validated['duration'];
        if (!in_array($subscription->status, [Subscription::STATUS_SUSPENDED, Subscription::STATUS_TERMINATED], true)) {
            $subscription->status = Subscription::STATUS_ACTIVE;
        }
        if (!empty($validated['notes'])) {
            $subscription->notes = $validated['notes'];
        }
        $subscription->save();

        $this->logAudit(
            AuditLog::ACTION_UPDATE,
            Subscription::class,
            $subscription->id,
            $old,
            [
                'action' => 'extend_subscription',
                'tenant' => $tenant->name,
                'duration_added' => $validated['duration'],
                'old_end_date' => $old['end_date'],
                'new_end_date' => $subscription->end_date->toDateString(),
            ]
        );

        return redirect()->route('developer.subscriptions.index')
            ->with('success', 'تم تمديد اشتراك ' . $tenant->name . ' حتى ' . $subscription->end_date->toDateString());
    }

    public function change(Request $request, Tenant $tenant): RedirectResponse
    {
        $subscription = $tenant->subscription;

        if (!$subscription) {
            return redirect()->route('developer.subscriptions.index')
                ->with('error', 'لا يوجد اشتراك لهذا العميل لتعديله.');
        }

        $validated = $request->validate([
            'duration' => 'nullable|integer|in:1,2,3,6,12',
            'end_date' => 'nullable|date|after_or_equal:today',
            'notes' => 'nullable|string|max:255',
        ]);

        $old = $subscription->toArray();

        if (!empty($validated['end_date'])) {
            $newEnd = Carbon::parse($validated['end_date']);
            if ($newEnd->lessThanOrEqualTo($subscription->start_date)) {
                return redirect()->route('developer.subscriptions.index')
                    ->with('error', 'تاريخ الانتهاء يجب أن يكون بعد تاريخ البداية.');
            }
            $subscription->end_date = $newEnd->toDateString();
            $subscription->plan_duration_months = max(1, (int) $subscription->start_date->diffInMonths($newEnd));
        } elseif (!empty($validated['duration'])) {
            $subscription->plan_duration_months = (int) $validated['duration'];
            $subscription->end_date = Subscription::endDateFor($subscription->start_date->toDateString(), (int) $validated['duration'])->toDateString();
        } else {
            return redirect()->route('developer.subscriptions.index')
                ->with('error', 'حدد مدة جديدة أو تاريخ انتهاء جديد.');
        }

        $subscription->status = Subscription::STATUS_ACTIVE;
        if (!empty($validated['notes'])) {
            $subscription->notes = $validated['notes'];
        }
        $subscription->save();

        $this->logAudit(
            AuditLog::ACTION_UPDATE,
            Subscription::class,
            $subscription->id,
            $old,
            [
                'action' => 'change_subscription',
                'tenant' => $tenant->name,
                'old_end_date' => $old['end_date'],
                'new_end_date' => $subscription->end_date->toDateString(),
                'duration' => $subscription->plan_duration_months,
            ]
        );

        return redirect()->route('developer.subscriptions.index')
            ->with('success', 'تم تعديل اشتراك ' . $tenant->name . ' حتى ' . $subscription->end_date->toDateString());
    }

    public function suspend(Tenant $tenant): RedirectResponse
    {
        $subscription = $tenant->subscription;

        if (!$subscription) {
            return redirect()->route('developer.subscriptions.index')
                ->with('error', 'لا يوجد اشتراك لهذا العميل.');
        }

        $old = $subscription->toArray();
        $subscription->status = Subscription::STATUS_SUSPENDED;
        $subscription->save();

        $this->logAudit(
            AuditLog::ACTION_UPDATE,
            Subscription::class,
            $subscription->id,
            $old,
            [
                'action' => 'suspend_subscription',
                'tenant' => $tenant->name,
                'end_date' => $subscription->end_date->toDateString(),
            ]
        );

        return redirect()->route('developer.subscriptions.index')
            ->with('success', 'تم إيقاف اشتراك ' . $tenant->name . ' يدويًا.');
    }

    public function reactivate(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'duration' => 'required|integer|in:1,2,3,6,12',
            'notes' => 'nullable|string|max:255',
        ]);

        $subscription = $tenant->subscription;

        if (!$subscription) {
            return redirect()->route('developer.subscriptions.index')
                ->with('error', 'لا يوجد اشتراك لهذا العميل لإعادة تفعيله.');
        }

        $old = $subscription->toArray();

        $subscription->status = Subscription::STATUS_ACTIVE;
        if ($subscription->end_date->lessThanOrEqualTo(now())) {
            $newEnd = Subscription::endDateFor(Carbon::today()->toDateString(), (int) $validated['duration']);
            $subscription->end_date = $newEnd->toDateString();
            $subscription->plan_duration_months = (int) $validated['duration'];
        }
        if (!empty($validated['notes'])) {
            $subscription->notes = $validated['notes'];
        }
        $subscription->save();

        $this->logAudit(
            AuditLog::ACTION_UPDATE,
            Subscription::class,
            $subscription->id,
            $old,
            [
                'action' => 'reactivate_subscription',
                'tenant' => $tenant->name,
                'old_status' => $old['status'],
                'new_status' => Subscription::STATUS_ACTIVE,
                'end_date' => $subscription->end_date->toDateString(),
            ]
        );

        return redirect()->route('developer.subscriptions.index')
            ->with('success', 'تمت إعادة تفعيل اشتراك ' . $tenant->name . ' حتى ' . $subscription->end_date->toDateString());
    }

    public function terminate(Tenant $tenant): RedirectResponse
    {
        $subscription = $tenant->subscription;

        if (!$subscription) {
            return redirect()->route('developer.subscriptions.index')
                ->with('error', 'لا يوجد اشتراك لهذا العميل.');
        }

        $old = $subscription->toArray();
        $subscription->status = Subscription::STATUS_TERMINATED;
        $subscription->save();

        $this->logAudit(
            AuditLog::ACTION_DELETE,
            Subscription::class,
            $subscription->id,
            $old,
            [
                'action' => 'terminate_subscription',
                'tenant' => $tenant->name,
                'end_date' => $subscription->end_date->toDateString(),
            ]
        );

        return redirect()->route('developer.subscriptions.index')
            ->with('success', 'تم إنهاء اشتراك ' . $tenant->name . ' بالكامل.');
    }

    public function storeTenant(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'owner_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
        ]);

        $tenant = Tenant::create([
            'name' => $validated['name'],
            'owner_name' => $validated['owner_name'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'status' => 'active',
        ]);

        $this->logAudit(
            AuditLog::ACTION_CREATE,
            Tenant::class,
            $tenant->id,
            null,
            $tenant->toArray()
        );

        return redirect()->route('developer.subscriptions.index')
            ->with('success', 'تمت إضافة العميل ' . $tenant->name . ' بنجاح.');
    }
}