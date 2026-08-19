<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\SubscriptionService;
use App\Traits\AuditLoggable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeveloperSubscriptionController extends Controller
{
    use AuditLoggable;

    public function config(): View
    {
        return view('developer.subscription.config', [
            'info' => app(SubscriptionService::class)->info(),
            'durations' => SubscriptionService::DURATION_OPTIONS,
        ]);
    }

    public function activate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'duration' => 'required|integer|in:1,2,3,6,12',
        ]);

        $service = app(SubscriptionService::class);
        $current = $service->status();

        // Never lose the remaining time of an active subscription without an explicit confirmation.
        if (in_array($current, [SubscriptionService::STATUS_ACTIVE, SubscriptionService::STATUS_EXPIRING], true) && !$request->boolean('confirm')) {
            return redirect()->route('developer.subscription.config')
                ->with('error', 'الاشتراك حاليًا نشط ومتبقي منه ' . $service->daysRemaining() . ' يوم. لتغيير المدة وفقدان الوقت المتبقي، عليك تأكيد التغيير أولًا.');
        }

        $old = $service->info();
        $result = $service->activate((int) $validated['duration']);

        $this->logAudit(
            AuditLog::ACTION_CREATE,
            Setting::class,
            null,
            $old,
            [
                'action' => 'activate_subscription',
                'duration' => $validated['duration'],
                'start_at' => $result['start']->toDateTimeString(),
                'end_at' => $result['end']->toDateTimeString(),
            ]
        );

        return redirect()->route('developer.subscription.config')
            ->with('success', 'تم تفعيل اشتراك النظام حتى ' . $result['end']->format('d/m/Y'));
    }

    public function suspend(): RedirectResponse
    {
        $service = app(SubscriptionService::class);

        if ($service->status() === SubscriptionService::STATUS_NONE) {
            return redirect()->route('developer.subscription.config')
                ->with('error', 'لا يوجد اشتراك مفعّل ليتم إيقافه.');
        }

        $old = $service->info();
        $service->suspend();

        $this->logAudit(
            AuditLog::ACTION_UPDATE,
            Setting::class,
            null,
            $old,
            ['action' => 'suspend_subscription', 'status' => SubscriptionService::STATUS_SUSPENDED]
        );

        return redirect()->route('developer.subscription.config')
            ->with('success', 'تم إيقاف اشتراك النظام يدويًا.');
    }

    public function reactivate(): RedirectResponse
    {
        $service = app(SubscriptionService::class);

        if ($service->status() !== SubscriptionService::STATUS_SUSPENDED) {
            return redirect()->route('developer.subscription.config')
                ->with('error', 'الاشتراك غير متوقف حاليًا.');
        }

        $old = $service->info();
        $service->reactivate();

        $this->logAudit(
            AuditLog::ACTION_UPDATE,
            Setting::class,
            null,
            $old,
            ['action' => 'reactivate_subscription', 'status' => SubscriptionService::STATUS_ACTIVE]
        );

        return redirect()->route('developer.subscription.config')
            ->with('success', 'تمت إعادة تفعيل اشتراك النظام.');
    }
}