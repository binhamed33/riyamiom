<?php

namespace App\Services;

use App\Models\Client;
use App\Models\FinanceInvoice;
use App\Models\LegalCase;
use App\Models\Session;
use App\Models\Task;
use App\Support\ArabicCount;
use Illuminate\Support\Collection;

class AttentionService
{
    public function items(?int $limit = null): Collection
    {
        try {
            return $this->buildItems($limit);
        } catch (\Throwable $e) {
            logger()->warning('AttentionService failed (degraded): ' . $e->getMessage(), ['exception' => $e]);
            return collect();
        }
    }

    private function buildItems(?int $limit = null): Collection
    {
        $user = auth()->user();
        if ($user && $user->isClient()) {
            return collect();
        }

        $now = now();
        $items = collect();
        $isLawyer = $user && $user->isLawyer();

        // 1. Session today/upcoming within 48h with no open prep task (critical if today)
        $window = $now->copy()->addHours(48);
        $sessions = Session::with('case')
            ->when($isLawyer, fn ($q) => $q->whereHas('case', fn ($cq) => $cq->where('lawyer_id', $user->id)))
            ->where('date', '>=', $now)
            ->where('date', '<=', $window)
            ->where('status', 'upcoming')
            ->orderBy('date')
            ->limit(10)
            ->get();

        foreach ($sessions as $s) {
            $prep = $s->case ? $this->preparation($s) : null;
            $hasPrep = $prep !== null;
            $isToday = $s->date->isSameDay($now);
            $items->push([
                'severity' => $isToday && !$hasPrep ? 'critical' : ($hasPrep ? 'info' : 'warning'),
                'title' => ($isToday ? 'جلسة اليوم' : 'جلسة قريبة') . ' — ' . ($s->case->case_number ?? 'قضية') . ' (' . ($s->location ?? '') . ')',
                'description' => $prep
                    ?? ($isToday ? 'جلسة اليوم بدون تحضير - أضف تحديثاً على القضية أو مهمة تحضير الآن' : 'جلسة خلال 48 ساعة بدون تحضير - أضف تحديثاً على القضية أو مهمة تحضير'),
                'url' => route('sessions.show', $s),
                'action' => !$hasPrep ? ['label' => 'تحضير الجلسة', 'url' => route('cases.show', $s->case_id) . '#quick'] : null,
                'icon' => 'gavel',
            ]);
        }

        // 2. Overdue tasks
        // ‏diffInDays(due, false) في Carbon 3 سالبٌ لتاريخٍ مضى: «متأخرة -6 يوم»،
        // وشرطُ «ثلاثة أيام فأكثر = عاجل» لا يتحقّق أبداً — فصار العددُ من المهمّة نفسِها
        Task::with(['case', 'assignee'])
            ->when($isLawyer, fn ($q) => $q->where('assigned_to', $user->id))
            ->overdue()
            ->orderBy('due_date')
            ->limit(5)
            ->get()
            ->each(function ($t) use ($items) {
                $days = $t->daysOverdue();
                $items->push([
                    'severity' => $days >= 3 ? 'critical' : 'warning',
                    'title' => 'مهمة متأخرة — ' . $t->title,
                    'description' => 'متأخرة ' . ArabicCount::daysAccusative($days) . ($t->case ? ' — ' . $t->case->case_number : ''),
                    'url' => route('tasks.show', $t),
                    'action' => ['label' => 'إنهاء المهمة', 'url' => route('tasks.show', $t)],
                    'icon' => 'task',
                ]);
            });

        // 3. Cases flagged overdue
        LegalCase::where('status', 'overdue')
            ->when($isLawyer, fn ($q) => $q->where('lawyer_id', $user->id))
            ->limit(5)->get()
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
        // الملغاةُ ليست غيرَ مدفوعة، والتأخّرُ يومُ استحقاقٍ قبل اليوم: كانت
        // الإشارةُ مقلوبةً فتُعلَّم فاتورةٌ تستحقّ بعد يومين «متأخرة 1 يوم»
        // وتمرّ المتأخّرةُ خمسةَ أشهرٍ «معلومةً» بلا كلمة
        $today = $now->copy()->startOfDay();
        FinanceInvoice::with('client')
            ->visibleTo($user)
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $today->copy()->addDays(7)->toDateString())
            ->orderBy('due_date')
            ->limit(5)
            ->get()
            ->each(function ($i) use ($items, $today) {
                $overdueDays = $i->due_date->lt($today) ? (int) $i->due_date->copy()->startOfDay()->diffInDays($today) : 0;
                $items->push([
                    'severity' => $overdueDays > 0 ? 'warning' : 'info',
                    'title' => 'فاتورة غير مدفوعة — ' . $i->invoice_number,
                    'description' => ($i->client->name ?? '') . ' — باقي ' . number_format($i->remaining_amount, 2)
                        . ($overdueDays > 0 ? ' (متأخرة ' . ArabicCount::daysAccusative($overdueDays) . ')' : ''),
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
                    // diffForHumans بالعربيّة تبدأ بـ«منذ»: «أُضيف قبل منذ يوم»
                    'description' => 'أُضيف ' . $c->created_at->diffForHumans() . ' — أنشئ قضية له',
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

    /**
     * أمُحضَّرةٌ الجلسة؟ — جملةُ الحال إن كانت، وإلا null.
     *
     * ═══ ما وقع ═══
     *
     * زرُّ «تحضير الجلسة» في التنبيه يفتح نافذةَ «تحديث» على القضية،
     * فيكتب المحامي ما حضّره ويحفظه — ويبقى التنبيه «بدون تحضير» كما
     * هو: الحكمُ كان بمهمّةٍ مفتوحةٍ عنوانُها «تحضير» وحدَها. فالتحديثُ
     * لا يُحتسب، والمهمّةُ إذا أُنجزت سقط الاحتسابُ أيضاً — فكان إتمامُ
     * التحضير يُعيد التنبيهَ إلى «بدون تحضير».
     *
     * ═══ القاعدة ═══
     *
     * تُعدّ محضَّرةً بواحدٍ من ثلاثة، بهذا الترتيب:
     * - مهمّةُ تحضيرٍ أُنجزت ⇐ «تم التحضير».
     * - تحديثٌ (نشاطٌ يكتبه موظّف: ملاحظة/اتّصال/…) على القضية بعد
     *   تحديد موعد الجلسة ⇐ «تم التحضير — تحديثٌ على القضية قبل …».
     * - مهمّةُ تحضيرٍ مفتوحة ⇐ «التحضير قيد التنفيذ».
     */
    private function preparation(Session $s): ?string
    {
        $done = Task::where('case_id', $s->case_id)
            ->where('status', 'completed')
            ->where('title', 'like', $this->prepKeyword($s))
            ->exists();

        if ($done) {
            return 'تم التحضير — مهمّة التحضير مُنجزة';
        }

        // منذ تحديدِ الجلسة لا منذ الأزل: تحديثٌ قديمٌ قبل الموعد ليس تحضيراً له
        $since = $s->created_at ?? $s->date->copy()->subDays(14);
        $update = \App\Models\CaseActivity::where('case_id', $s->case_id)
            ->whereIn('type', [\App\Models\CaseActivity::TYPE_NOTE, \App\Models\CaseActivity::TYPE_CALL, \App\Models\CaseActivity::TYPE_APPOINTMENT, \App\Models\CaseActivity::TYPE_OTHER])
            ->whereNotNull('user_id')
            ->where('created_at', '>=', $since)
            ->latest('id')
            ->first();

        if ($update) {
            return 'تم التحضير — تحديثٌ على القضية ' . $update->created_at->diffForHumans() . ': ' . mb_substr($update->title, 0, 60);
        }

        $open = Task::where('case_id', $s->case_id)
            ->where('status', '!=', 'completed')
            ->where('title', 'like', $this->prepKeyword($s))
            ->exists();

        return $open ? 'التحضير قيد التنفيذ — مهمّة تحضير مفتوحة' : null;
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