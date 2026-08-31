<?php

namespace App\Services\ClientPortal;

use App\Models\Client;
use App\Models\Document;
use App\Models\LegalCase;
use App\Support\ClientPortal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * البوابة الوحيدة التي تمرّ منها بيانات العميل.
 *
 * السبب: عندما تتفرّق شروط الصلاحية في المتحكّمات والقوالب، يكفي أن
 * ينسى موضع واحد شرطاً ليتسرّب مستند أو قضية. فكل استعلام هنا يبدأ
 * من العميل ويُقيَّد به، ولا يوجد مسار يجلب قضيةً بمعرّفها وحده.
 *
 * العزل بين المكاتب أعلى من هذه الطبقة أصلاً: كل مكتب قاعدة بيانات
 * مستقلة في نسخة مستقلة، فلا يوجد صفّ يخصّ مكتباً آخر في هذا الجدول
 * لتُقصيه. هذه الطبقة تعزل العملاء بعضهم عن بعض داخل المكتب الواحد.
 */
class ClientCaseGateway
{
    public function __construct(private Client $client)
    {
    }

    public static function for(Client $client): self
    {
        return new self($client);
    }

    /** أساس كل استعلام: قضايا هذا العميل وحده */
    public function cases(): Builder
    {
        return LegalCase::query()->where('client_id', $this->client->id);
    }

    /**
     * قضية بعينها — أو null.
     *
     * لا تُستقبَل نماذج مُحقونة من المسار: نبحث بالمعرّف داخل نطاق
     * العميل، فمعرّف قضية عميل آخر لا يُرجع شيئاً بدل أن يُرجع نموذجاً
     * ثم نتذكّر فحصه.
     */
    public function findCase(int|string $id): ?LegalCase
    {
        return $this->cases()->whereKey($id)->first();
    }

    /** الجلسات القادمة عبر كل قضايا العميل */
    public function upcomingSessions(int $limit = 5): Collection
    {
        if (!ClientPortal::showsSessions()) {
            return new Collection();
        }

        return \App\Models\Session::query()
            ->whereIn('case_id', $this->cases()->select('id'))
            ->where(function ($q) {
                $q->where('status', 'upcoming')->orWhere('date', '>=', now()->startOfDay());
            })
            ->whereNotIn('status', ['cancelled'])
            ->with('case:id,title,case_number')
            ->orderBy('date')
            ->limit($limit)
            ->get();
    }

    public function sessionsFor(LegalCase $case): Collection
    {
        if (!ClientPortal::showsSessions()) {
            return new Collection();
        }

        return $case->sessions()->orderByDesc('date')->get();
    }

    /**
     * مستندات القضية المسموح للعميل برؤيتها.
     *
     * شرطان معاً: المكتب فعّل عرض المستندات، والمستند نفسه عُلّم
     * client_visible. ومستند خاص (private) لا يُعرض مهما كان العلَم —
     * حزام وحمّالة، فخطأ في مكان واحد لا يكفي للتسريب.
     */
    public function documentsFor(LegalCase $case): Collection
    {
        if (!ClientPortal::showsDocuments()) {
            return new Collection();
        }

        return $case->documents()
            ->where('client_visible', true)
            ->where('access_level', '!=', Document::ACCESS_PRIVATE)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * §13: محاسبة القضية كما يراها الموكّل — البندُ المعلَّم وحده،
     * وبعد أن يفتح المكتب القسم من إعدادات البوابة.
     *
     * @return array{fees: Collection, invoices: Collection, total: float, paid: float, due: float}
     */
    public function accountingFor(LegalCase $case): array
    {
        $empty = ['fees' => new Collection(), 'invoices' => new Collection(), 'total' => 0.0, 'paid' => 0.0, 'due' => 0.0];

        if (!ClientPortal::showsAccounting()) {
            return $empty;
        }

        $fees = $case->fees()->visibleToClient()->get();
        $invoices = $case->invoices()->visibleToClient()->get();

        if ($fees->isEmpty() && $invoices->isEmpty()) {
            return $empty;
        }

        $total = (float) ($fees->sum('amount') + $invoices->sum('amount'));
        $paid = (float) ($fees->where('status', 'paid')->sum('amount') + $invoices->sum('paid_amount'));

        return [
            'fees' => $fees,
            'invoices' => $invoices,
            'total' => $total,
            'paid' => $paid,
            'due' => max(0.0, $total - $paid),
        ];
    }

    /**
     * ما على الموكّل في قضاياه كلِّها — لا في قضيّةٍ واحدة.
     *
     * ═══ لماذا مجموعٌ لا تفصيلٌ فقط ═══
     *
     * الموكّلُ لا يحمل في رأسه أرقامَ ثلاث قضايا. سؤالُه الذي يهاتف
     * المكتبَ لأجله واحد: «كم عليّ؟». وتفصيلٌ بلا مجموعٍ يجعله يجمع
     * بنفسه — أو يهاتف، وهو ما وُضعت البوابة لتغنيه عنه.
     *
     * ═══ وما لا يُعرض ═══
     *
     * ما لم يعلّمه المكتبُ «مرئياً للموكّل» لا يدخل في الحساب أصلاً:
     * لا في المجموع ولا في التفصيل. فرسمٌ داخليٌّ قيد المراجعة لا
     * يظهر رقماً في شاشة الموكّل قبل أن يقرّره المكتب.
     *
     * @return array{total: float, paid: float, due: float, items: Collection, cases: int}
     */
    public function duesSummary(): array
    {
        $empty = ['total' => 0.0, 'paid' => 0.0, 'due' => 0.0, 'items' => new Collection(), 'cases' => 0];

        if (!ClientPortal::showsAccounting()) {
            return $empty;
        }

        $cases = $this->cases()->get(['id', 'case_number', 'office_case_number', 'title']);

        if ($cases->isEmpty()) {
            return $empty;
        }

        $byCase = $cases->keyBy('id');
        $items = new Collection();
        $total = 0.0;
        $paid = 0.0;

        foreach ($cases as $case) {
            $accounting = $this->accountingFor($case);

            $total += (float) $accounting['total'];
            $paid += (float) $accounting['paid'];

            foreach ($accounting['invoices'] as $invoice) {
                $items->push([
                    'kind' => 'invoice',
                    'label' => (string) ($invoice->invoice_number ?: '—'),
                    'amount' => (float) $invoice->amount,
                    'remaining' => (float) max(0, $invoice->amount - $invoice->paid_amount),
                    'date' => $invoice->issue_date,
                    'case' => $byCase[$case->id] ?? null,
                ]);
            }

            foreach ($accounting['fees'] as $fee) {
                $items->push([
                    'kind' => 'fee',
                    'label' => (string) ($fee->description ?: $fee->fee_type ?: '—'),
                    'amount' => (float) $fee->amount,
                    'remaining' => $fee->status === 'paid' ? 0.0 : (float) $fee->amount,
                    'date' => $fee->date ?? $fee->created_at,
                    'case' => $byCase[$case->id] ?? null,
                ]);
            }
        }

        // غيرُ المسدَّد أوّلاً: هو ما يعني الموكّل، والمسدَّدُ سجلٌّ
        $items = $items->sortByDesc('remaining')->values();

        return [
            'total' => $total,
            'paid' => $paid,
            'due' => max(0.0, $total - $paid),
            'items' => $items,
            'cases' => $cases->count(),
        ];
    }

    /** مستند بعينه — يمرّ بكل شروط العرض قبل أي تنزيل */
    public function findDocument(int|string $documentId): ?Document
    {
        if (!ClientPortal::showsDocuments()) {
            return null;
        }

        return Document::query()
            ->whereKey($documentId)
            ->whereIn('case_id', $this->cases()->select('id'))
            ->where('client_visible', true)
            ->where('access_level', '!=', Document::ACCESS_PRIVATE)
            ->first();
    }

    /**
     * المسار الزمني — أحداث مسموح بها فقط.
     *
     * قائمة سماح: أي نوع حدث جديد يبقى محجوباً حتى يُدرَج عمداً.
     * ملاحظات المحامي الداخلية ومهامّه وسجلّ التدقيق لا تمرّ من هنا
     * أصلاً لأنها ليست ضمن الأنواع المسموحة.
     */
    public function timelineFor(LegalCase $case, int $limit = 40): Collection
    {
        if (!ClientPortal::showsTimeline()) {
            return new Collection();
        }

        return \App\Models\CaseActivity::query()
            ->where('case_id', $case->id)
            ->whereIn('type', ClientPortal::clientVisibleActivityTypes())
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /** ملخّص لوحة العميل — أرقام قليلة ذات معنى، لا لوحة أرقام */
    public function summary(): array
    {
        $open = [
            LegalCase::STATUS_ACTIVE,
            LegalCase::STATUS_PENDING,
            LegalCase::STATUS_OVERDUE,
        ];

        return [
            'total' => (clone $this)->cases()->count(),
            'active' => (clone $this)->cases()->whereIn('status', $open)->count(),
            'upcoming_sessions' => ClientPortal::showsSessions()
                ? \App\Models\Session::whereIn('case_id', $this->cases()->select('id'))
                    ->where('date', '>=', now()->startOfDay())
                    ->whereNotIn('status', ['cancelled'])
                    ->count()
                : 0,
        ];
    }

    /** آخر القضايا تحديثاً — ما يهمّ العميل أولاً */
    public function recentlyUpdated(int $limit = 3): Collection
    {
        return $this->cases()
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();
    }
}
