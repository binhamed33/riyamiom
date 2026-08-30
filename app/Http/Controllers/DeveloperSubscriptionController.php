<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\SubscriptionService;
use App\Traits\AuditLoggable;
use Carbon\Carbon;
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
            'duration' => 'nullable|integer|in:1,2,3,6,12',
            'custom_end' => 'nullable|date|after:today',
        ]);

        $months = $validated['duration'] ?? null;
        $customEnd = isset($validated['custom_end']) ? Carbon::parse($validated['custom_end'])->endOfDay() : null;

        if (!$months && !$customEnd) {
            return redirect()->route('developer.subscription.config')
                ->with('error', 'اختر مدة الاشتراك أو حدّد تاريخ انتهاء مخصصًا.');
        }

        $service = app(SubscriptionService::class);
        $current = $service->status();

        // Never lose the remaining time of an active subscription without an explicit confirmation.
        if (in_array($current, [SubscriptionService::STATUS_ACTIVE, SubscriptionService::STATUS_EXPIRING], true) && !$request->boolean('confirm')) {
            return redirect()->route('developer.subscription.config')
                ->with('error', 'الاشتراك حاليًا نشط ومتبقي منه ' . $service->daysRemaining() . ' يوم. لتغيير المدة وفقدان الوقت المتبقي، عليك تأكيد التغيير أولًا.');
        }

        $old = $service->info();
        $result = $service->activate((int) ($months ?? 0), $customEnd);

        $this->logAudit(
            AuditLog::ACTION_CREATE,
            Setting::class,
            null,
            $old,
            [
                'action' => 'activate_subscription',
                'duration' => $months,
                'custom_end' => $customEnd?->toDateTimeString(),
                'start_at' => $result['start']->toDateTimeString(),
                'end_at' => $result['end']->toDateTimeString(),
            ]
        );

        return redirect()->route('developer.subscription.config')
            ->with('success', 'تم تفعيل اشتراك النظام حتى ' . $result['end']->format('d/m/Y'));
    }

    public function extend(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'duration' => 'nullable|integer|in:1,2,3,6,12',
            'custom_end' => 'nullable|date|after:today',
        ]);

        $service = app(SubscriptionService::class);

        if ($service->status() === SubscriptionService::STATUS_NONE) {
            return redirect()->route('developer.subscription.config')
                ->with('error', 'لا يوجد اشتراك لتمديده — فعّل اشتراكًا أولًا.');
        }

        $months = $validated['duration'] ?? null;
        $customEnd = isset($validated['custom_end']) ? Carbon::parse($validated['custom_end'])->endOfDay() : null;

        if (!$months && !$customEnd) {
            return redirect()->route('developer.subscription.config')
                ->with('error', 'اختر مدة التمديد أو حدّد تاريخ انتهاء مخصصًا.');
        }

        $old = $service->info();
        $result = $service->extend((int) ($months ?? 0), $customEnd);

        $this->logAudit(
            AuditLog::ACTION_UPDATE,
            Setting::class,
            null,
            $old,
            [
                'action' => 'extend_subscription',
                'added_months' => $months,
                'custom_end' => $customEnd?->toDateTimeString(),
                'end_at' => $result['end']->toDateTimeString(),
            ]
        );

        return redirect()->route('developer.subscription.config')
            ->with('success', 'تم تمديد الاشتراك حتى ' . $result['end']->format('d/m/Y'));
    }

    public function expire(): RedirectResponse
    {
        $service = app(SubscriptionService::class);

        if (in_array($service->status(), [SubscriptionService::STATUS_NONE, SubscriptionService::STATUS_EXPIRED], true)) {
            return redirect()->route('developer.subscription.config')
                ->with('error', 'لا يوجد اشتراك سارٍ لإنهائه.');
        }

        $old = $service->info();
        $service->expire();

        $this->logAudit(
            AuditLog::ACTION_UPDATE,
            Setting::class,
            null,
            $old,
            ['action' => 'expire_subscription', 'status' => SubscriptionService::STATUS_EXPIRED]
        );

        return redirect()->route('developer.subscription.config')
            ->with('success', 'تم إنهاء الاشتراك فورًا — النظام مقفل الآن على غير المطوّرين.');
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