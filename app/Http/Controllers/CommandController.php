<?php

namespace App\Http\Controllers;

use App\Models\CaseActivity;
use App\Models\Client;
use App\Models\Document;
use App\Models\FinanceInvoice;
use App\Models\LegalCase;
use App\Models\Session;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommandController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            return $this->search($request);
        } catch (\Throwable $e) {
            logger()->warning('CommandController degraded: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'groups' => [],
                'actions' => $this->actions($request->user()),
                'empty' => true,
            ]);
        }
    }

    private function search(Request $request): JsonResponse
    {
        $raw = trim($request->get('q', ''));
        $query = str_replace(['%', '_'], ['\\%', '\\_'], $raw);
        $user = $request->user();
        $isLawyer = $user && $user->isLawyer();

        $groups = [];

        if (strlen($query) < 2) {
            return response()->json([
                'groups' => $this->recentGroups($user),
                'actions' => $this->actions($user),
                'empty' => true,
            ]);
        }

        // ═══ أقسامُ الموقع أوّلاً ═══
        //
        // البحثُ كان يقرأ الصفوفَ وحدَها، فمن كتب «مداولة» أو «مواعيد»
        // أو «نسخ احتياطي» لم يجد شيئاً — والصفحةُ أمامه في القائمة.
        // وهي أوّلُ المجموعات لأنّ من كتب اسمَ قسمٍ يريد القسمَ لا
        // صفّاً صادف أنّ فيه الكلمة.
        $pages = \App\Support\SearchPages::match($raw, $user);
        if ($pages !== []) {
            $groups['page'] = $pages;
        }

        $canFull = in_array($user->role, ['developer', 'admin', 'lawyer', 'staff']);

        if ($canFull) {
            $cases = LegalCase::with('client')->where(function ($q) use ($query) {
                $q->where('case_number', 'like', "%{$query}%")
                  ->orWhere('office_case_number', 'like', "%{$query}%")
                  ->orWhere('title', 'like', "%{$query}%")
                  ->orWhere('court', 'like', "%{$query}%")
                  ->orWhere('opponent_phone', 'like', "%{$query}%");
            })->limit(5)->get();

            if ($cases->isNotEmpty()) {
                foreach ($cases as $c) {
                    $label = '#' . $c->office_case_number . ' - ' . $c->title;
                    if ($c->client) $label .= ' - ' . $c->client->name;
                    $groups['case'][] = [
                        'label' => $label,
                        'sub' => ($c->court ?? '') . ($c->status ? ' • ' . $c->status : ''),
                        'url' => route('cases.show', $c),
                        'icon' => 'ق',
                    ];
                }
            }

            $clients = Client::where('name', 'like', "%{$query}%")->limit(5)->get();
            foreach ($clients as $c) {
                $groups['client'][] = [
                    'label' => $c->name,
                    'sub' => $c->phone,
                    'url' => route('clients.show', $c),
                    'icon' => 'ع',
                ];
            }

            $sessions = Session::with('case')
                ->when($isLawyer, fn ($q) => $q->whereHas('case', fn ($cq) => $cq->where('lawyer_id', $user->id)))
                ->where('location', 'like', "%{$query}%")
                ->orderByDesc('date')->limit(5)->get();
            foreach ($sessions as $s) {
                $groups['session'][] = [
                    'label' => ($s->case->case_number ?? 'قضية') . ' - ' . ($s->location ?? ''),
                    'sub' => optional($s->date)->format('Y-m-d H:i'),
                    'url' => route('sessions.show', $s),
                    'icon' => 'ج',
                ];
            }

            $tasks = Task::with('case')
                ->when($isLawyer, fn ($q) => $q->where('assigned_to', $user->id))
                ->where(function ($q) use ($query) {
                    $q->where('title', 'like', "%{$query}%")
                        ->orWhere('description', 'like', "%{$query}%");
                })
                ->limit(5)->get();
            foreach ($tasks as $t) {
                $groups['task'][] = [
                    'label' => $t->title,
                    'sub' => ($t->case->case_number ?? '') . ($t->due_date ? ' • ' . $t->due_date->format('Y-m-d') : ''),
                    'url' => route('tasks.show', $t),
                    'icon' => 'م',
                ];
            }

            // بحثُ لوح الأوامر كان بلا visibleTo: يُكتب حرفٌ فتُعاد
            // عناوينُ مستنداتٍ خاصّةٍ بغيره. ومن مشى على حروف
            // المعجم استخرجها كلَّها.
            $documents = Document::with('case')
                ->visibleTo($user)
                ->when($isLawyer, fn ($q) => $q->whereHas('case', fn ($cq) => $cq->where('lawyer_id', $user->id)))
                ->where(function ($q) use ($query) {
                    $q->where('title', 'like', "%{$query}%")
                        ->orWhere('file_path', 'like', "%{$query}%");
                })
                ->limit(5)->get();
            foreach ($documents as $d) {
                $groups['document'][] = [
                    'label' => $d->title,
                    'sub' => ($d->case->case_number ?? '') . ' • ' . $d->file_type,
                    'url' => route('cases.show', ['case' => $d->case_id, '#documents']),
                    'icon' => 'و',
                ];
            }

            $invoices = FinanceInvoice::with('client')
                ->where('invoice_number', 'like', "%{$query}%")
                ->orWhere('description', 'like', "%{$query}%")
                ->limit(5)->get();
            foreach ($invoices as $i) {
                $groups['invoice'][] = [
                    'label' => $i->invoice_number . ' - ' . ($i->client->name ?? ''),
                    'sub' => number_format($i->amount, 2) . ($i->status ? ' • ' . $i->status : ''),
                    'url' => route('finance.index'),
                    'icon' => 'ف',
                ];
            }
        }

        $activities = CaseActivity::with(['case', 'user'])
            ->when($isLawyer, fn ($q) => $q->whereHas('case', fn ($cq) => $cq->where('lawyer_id', $user->id)))
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('content', 'like', "%{$query}%");
            })
            ->latest('occurred_at')->limit(5)->get();
        foreach ($activities as $a) {
            $groups['activity'][] = [
                'label' => $a->title,
                'sub' => ($a->case->case_number ?? '') . ($a->user ? ' • ' . $a->user->name : ''),
                'url' => route('cases.show', ['case' => $a->case_id, '#timeline']),
                'icon' => 'ن',
            ];
        }

        if ($groups === []) {
            $groups['empty'][] = ['label' => __('لا توجد نتائج'), 'sub' => '', 'url' => null, 'icon' => '؟'];
        }

        return response()->json([
            'groups' => $groups,
            'actions' => $this->actions($user),
        ]);
    }

    private function recentGroups($user): array
    {
        $groups = [];
        $isLawyer = $user && $user->isLawyer();

        $cases = LegalCase::with('client')
            ->when($isLawyer, fn ($q) => $q->where('lawyer_id', $user->id))
            ->whereIn('status', ['active', 'pending', 'overdue', 'fees_pending'])
            ->latest()->limit(4)->get();
        foreach ($cases as $c) {
            $groups['recent-case'][] = [
                'label' => '#' . $c->office_case_number . ' - ' . $c->title,
                'sub' => ($c->client->name ?? '') . ($c->priority ? ' • ' . $c->priority : ''),
                'url' => route('cases.show', $c),
                'icon' => 'ق',
            ];
        }

        $upcoming = Session::with('case')
            ->when($isLawyer, fn ($q) => $q->whereHas('case', fn ($cq) => $cq->where('lawyer_id', $user->id)))
            ->where('date', '>=', now())->orderBy('date')->limit(3)->get();
        foreach ($upcoming as $s) {
            $groups['recent-session'][] = [
                'label' => ($s->case->case_number ?? 'قضية') . ' - ' . ($s->location ?? ''),
                'sub' => optional($s->date)->format('Y-m-d H:i'),
                'url' => route('sessions.show', $s),
                'icon' => 'ج',
            ];
        }

        $overdueTasks = Task::where('status', '!=', 'completed')
            ->when($isLawyer, fn ($q) => $q->where('assigned_to', $user->id))
            ->where('due_date', '<', now())
            ->orderBy('due_date')->limit(3)->get();
        foreach ($overdueTasks as $t) {
            $groups['recent-task'][] = [
                'label' => $t->title,
                'sub' => 'متأخرة • ' . optional($t->due_date)->format('Y-m-d'),
                'url' => route('tasks.show', $t),
                'icon' => 'م',
            ];
        }

        return $groups;
    }

    private function actions($user): array
    {
        $actions = [];

        $actions[] = ['key' => 'new_case', 'label' => __('إنشاء قضية جديدة'), 'icon' => '+', 'url' => route('cases.create')];
        $actions[] = ['key' => 'new_client', 'label' => __('إضافة موكل جديد'), 'icon' => '+', 'url' => route('clients.create')];
        $actions[] = ['key' => 'new_session', 'label' => __('تسجيل جلسة'), 'icon' => '+', 'url' => route('sessions.create')];
        $actions[] = ['key' => 'new_task', 'label' => __('إنشاء مهمة'), 'icon' => '+', 'url' => route('tasks.create')];
        $actions[] = ['key' => 'new_finance', 'label' => __('فاتورة / مالية'), 'icon' => '+', 'url' => route('finance.index')];

        return $actions;
    }
}