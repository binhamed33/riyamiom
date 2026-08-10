<?php

namespace App\Services;

use App\Models\Client;
use App\Models\FinanceInvoice;
use App\Models\LegalCase;
use App\Models\Session;
use App\Models\Task;
use Illuminate\Support\Collection;

class AttentionService
{
    public function items(?int $limit = null): Collection
    {
        $user = auth()->user();
        if ($user && $user->isClient()) {
            return collect();
        }

        $now = now();
        $items = collect();

        // 1. Session today/upcoming within 48h with no open prep task (critical if today)
        $window = $now->copy()->addHours(48);
        $sessions = Session::with('case')
            ->where('date', '>=', $now)
            ->where('date', '<=', $window)
            ->where('status', 'upcoming')
            ->orderBy('date')
            ->limit(10)
            ->get();

        foreach ($sessions as $s) {
            $hasPrep = $s->case && Task::where('case_id', $s->case_id)
                ->where('status', '!=', 'completed')
                ->where('title', 'like', $this->prepKeyword($s))
                ->exists();
            $isToday = $s->date->isSameDay($now);
            $items->push([
                'severity' => $isToday && !$hasPrep ? 'critical' : ($hasPrep ? 'info' : 'warning'),
                'title' => ($isToday ? 'جلسة اليوم' : 'جلسة قريبة') . ' — ' . ($s->case->case_number ?? 'قضية') . ' (' . ($s->location ?? '') . ')',
                'description' => $hasPrep
                    ? 'يوجد تحضير مرتبط بالقضية'
                    : ($isToday ? 'جلسة اليوم بدون تحضير - أنشئ مهمة تحضير الآن' : 'جلسة خلال 48 ساعة بدون تحضير - أنشئ مهمة تحضير'),
                'url' => route('sessions.show', $s),
                'action' => !$hasPrep ? ['label' => 'تحضير الجلسة', 'url' => route('cases.show', $s->case_id) . '#quick'] : null,
                'icon' => 'gavel',
            ]);
        }

        // 2. Overdue tasks
        Task::with(['case', 'assignee'])
            ->where('status', '!=', 'completed')
            ->where('due_date', '<', $now)
            ->orderBy('due_date')
            ->limit(5)
            ->get()
            ->each(function ($t) use ($items, $now) {
                $days = (int) $now->diffInDays($t->due_date, false);
                $items->push([
                    'severity' => $days >= 3 ? 'critical' : 'warning',
                    'title' => 'مهمة متأخرة — ' . $t->title,
                    'description' => 'متأخرة ' . $days . ' يوم' . ($t->case ? ' — ' . $t->case->case_number : ''),
                    'url' => route('tasks.show', $t),
                    'action' => ['label' => 'إنهاء المهمة', 'url' => route('tasks.show', $t)],
                    'icon' => 'task',
                ]);
            });

        // 3. Cases flagged overdue
        LegalCase::where('status', 'overdue')->limit(5)->get()
            ->each(function ($c) use ($items) {
                $items->push([
                    'severity' => 'warning',
                    'title' => 'قضية متعثرة — ' . $c->title,
                    'description' => '#' . $c->office_case_number . ' (' . ($c->court ?? '') . ')',
                    'url' => route('cases.show', $c),
                    'action' => ['label' => 'عرض القضية', 'url' => route('cases.show', $c)],
                    'icon' => 'case',
                ]);
            });

        // 4. Unpaid invoices (due soon or overdue)
        FinanceInvoice::with('client')
            ->where('status', '!=', 'paid')
            ->where(function ($q) use ($now) {
                $q->where('due_date', '<=', $now->copy()->addDays(7));
            })
            ->orderBy('due_date')
            ->limit(5)
            ->get()
            ->each(function ($i) use ($items, $now) {
                $overdueDays = $i->due_date ? (int) $now->diffInDays($i->due_date, false) : 0;
                $items->push([
                    'severity' => $overdueDays > 0 ? 'warning' : 'info',
                    'title' => 'فاتورة غير مدفوعة — ' . $i->invoice_number,
                    'description' => ($i->client->name ?? '') . ' — باقي ' . number_format($i->remaining_amount, 2)
                        . ($overdueDays > 0 ? ' (متأخرة ' . $overdueDays . ' يوم)' : ''),
                    'url' => route('finance.index'),
                    'action' => ['label' => 'الفاتورة', 'url' => route('finance.index')],
                    'icon' => 'invoice',
                ]);
            });

        // 5. New clients (last 7 days) with no cases
        Client::where('created_at', '>=', $now->copy()->subDays(7))
            ->whereDoesntHave('cases')
            ->limit(5)
            ->get()
            ->each(function ($c) use ($items) {
                $items->push([
                    'severity' => 'info',
                    'title' => 'موكل جديد بدون قضية — ' . $c->name,
                    'description' => 'أُضيف قبل ' . $c->created_at->diffForHumans() . ' — أنشئ قضية له',
                    'url' => route('clients.show', $c),
                    'action' => ['label' => 'قضية جديدة', 'url' => route('cases.create', ['client_id' => $c->id])],
                    'icon' => 'client',
                ]);
            });

        // 6. Recent client-portal messages (conversations) — latest first
        /* Skipped: covers chat module */
        // (case_activities activities are the actionable ones)

        $sorted = $items->sortBy(fn ($i) => ['critical' => 0, 'warning' => 1, 'info' => 2][$i['severity']] ?? 3)
            ->values();

        return is_null($limit) ? $sorted : $sorted->take($limit);
    }

    private function prepKeyword(Session $s): string
    {
        return '%تحضير%';
    }

    public static function itemsCount(): int
    {
        if (!auth()->check() || auth()->user()->isClient()) {
            return 0;
        }
        return (int) \Illuminate\Support\Facades\Cache::remember(
            'attention_count_' . auth()->id(),
            120,
            fn () => (new self())->items()->count()
        );
    }
}