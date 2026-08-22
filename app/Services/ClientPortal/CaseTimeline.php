<?php

namespace App\Services\ClientPortal;

use App\Models\LegalCase;
use App\Support\ClientPortal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * مسار القضية الزمني كما يراه العميل.
 *
 * لا يُبنى من سجلّ الأنشطة وحده: في كثير من المكاتب يكون هذا السجلّ
 * شحيحاً، فيخرج للعميل مسار فارغ رغم أن قضيته تتحرّك. لذلك يُركَّب
 * المسار من وقائع يملك العميل حقّ معرفتها أصلاً:
 *
 *   • فتح القضية        (opened_at / created_at)
 *   • كل جلسة وحالتها   (court_sessions)
 *   • الأنشطة المسموحة  (session / appointment / document)
 *
 * وما لا يظهر هنا: ملاحظات المحامي الداخلية، المهامّ، المكالمات،
 * الشؤون المالية، وسجلّ التدقيق — ليست ضمن المصادر أصلاً، لا أنها
 * تُرشَّح لاحقاً.
 */
class CaseTimeline
{
    public function __construct(private ClientCaseGateway $gateway)
    {
    }

    /** @return Collection<int, array{at: Carbon, kind: string, title: string, detail: ?string, state: string}> */
    public function build(LegalCase $case): Collection
    {
        if (!ClientPortal::showsTimeline()) {
            return collect();
        }

        $events = collect();

        // ١) فتح القضية
        $opened = $case->opened_at ?? $case->created_at;
        if ($opened) {
            $events->push([
                'at' => Carbon::parse($opened),
                'kind' => 'opened',
                'title' => __('portal.timeline.opened'),
                'detail' => $case->case_number ? __('portal.timeline.case_number', ['number' => $case->case_number]) : null,
                'state' => 'done',
            ]);
        }

        // ٢) الجلسات — ماضيها وقادمها
        foreach ($this->gateway->sessionsFor($case) as $session) {
            if (!$session->date) {
                continue;
            }

            $at = Carbon::parse($session->date);
            $future = $at->isFuture();

            $events->push([
                'at' => $at,
                'kind' => 'session',
                'title' => match ($session->status) {
                    'completed' => __('portal.timeline.session_held'),
                    'postponed' => __('portal.timeline.session_postponed'),
                    'cancelled' => __('portal.timeline.session_cancelled'),
                    default => $future ? __('portal.timeline.session_upcoming') : __('portal.timeline.session'),
                },
                'detail' => $session->location ?: null,
                'state' => $session->status === 'cancelled' ? 'cancelled' : ($future ? 'upcoming' : 'done'),
            ]);
        }

        // ٣) الأنشطة المسموح بها
        foreach ($this->gateway->timelineFor($case) as $activity) {
            $at = $activity->occurred_at ?? $activity->created_at;
            if (!$at) {
                continue;
            }

            $events->push([
                'at' => Carbon::parse($at),
                'kind' => $activity->type,
                'title' => $activity->title ?: __('portal.timeline.update'),
                // المحتوى الداخلي لا يُعرض — العنوان يكفي خبراً
                'detail' => null,
                'state' => 'done',
            ]);
        }

        // ٤) آخر تحديث للقضية — يطمئن العميل أن الملف حيّ
        if ($case->updated_at) {
            $events->push([
                'at' => Carbon::parse($case->updated_at),
                'kind' => 'updated',
                'title' => __('portal.timeline.last_update'),
                'detail' => null,
                'state' => 'done',
            ]);
        }

        return $events
            ->unique(fn ($e) => $e['kind'] . '|' . $e['at']->timestamp . '|' . $e['title'])
            ->sortByDesc(fn ($e) => $e['at']->timestamp)
            ->values();
    }
}
