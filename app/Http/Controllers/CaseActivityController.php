<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CaseActivity;
use App\Models\Document;
use App\Models\LegalCase;
use App\Models\Session;
use App\Models\Task;
use App\Traits\AuditLoggable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CaseActivityController extends Controller
{
    use AuditLoggable;

    public function store(Request $request, LegalCase $case): JsonResponse
    {
        $this->authorizeCaseActivity($case);

        $validated = $request->validate([
            'type'    => 'required|in:note,call,appointment,other',
            'title'   => 'required|string|max:255',
            'content' => 'nullable|string|max:2000',
        ]);

        $activity = CaseActivity::create([
            'case_id'     => $case->id,
            'user_id'     => auth()->id(),
            'type'        => $validated['type'],
            'title'       => $validated['title'],
            'content'     => $validated['content'] ?? null,
            'occurred_at' => now(),
        ]);

        $this->logAudit(
            AuditLog::ACTION_CREATE,
            CaseActivity::class,
            $activity->id,
            null,
            ['case_id' => $case->id, 'type' => $activity->type, 'title' => $activity->title]
        );

        return response()->json([
            'ok'      => true,
            'message' => 'activity_created',
            'id'      => $activity->id,
        ]);
    }

    public function timeline(LegalCase $case): JsonResponse
    {
        $this->authorizeCaseActivity($case);
        // ═══ العنوانُ وحدَه إفشاء ═══
        //
        // مستوى الإتاحة هو الحارسُ الوحيد بين موظّفي المكتب: لا فحصَ
        // على مستوى القضية (كلُّ الفريق يصل إلى كلّ قضية بالقصد). فإن
        // سقط، سقط كلُّ شيء.
        //
        // وثلاثةُ مسارات كانت تحمّل المستنداتِ بلا visibleTo بينما
        // تطبّقه cases.show وملفُّ القضية. والمُسرَّبُ ليس الملفَّ بل
        // عنوانَه — و«طلب طلاق» أو «تقرير طبّي» في قضيةِ زميلٍ يكفي
        // وحدَه: يُعرَف الموضوعُ من الاسم بلا فتح.
        $case->load([
            'activities.user', 'sessions', 'tasks',
            'documents' => fn ($q) => $q->visibleTo(auth()->user()),
        ]);

        $events = collect();

        $case->activities->each(function ($a) use ($events, $case) {
            $events->push([
                'kind'  => 'activity',
                'title' => $a->title,
                'sub'   => $a->type . ($a->user ? ' • ' . $a->user->name : ''),
                'date'  => $a->occurred_at,
                'id'    => 'a' . $a->id,
            ]);
        });

        $case->sessions->each(function ($s) use ($events, $case) {
            $events->push([
                'kind'  => 'session',
                'title' => 'جلسة — ' . ($s->location ?? '-'),
                'sub'   => $s->status . ($s->notes ? ' • ' . $s->notes : ''),
                'date'  => $s->date,
                'id'    => 's' . $s->id,
            ]);
        });

        $case->tasks->each(function ($t) use ($events, $case) {
            $events->push([
                'kind'  => 'task',
                'title' => 'مهمة — ' . $t->title,
                'sub'   => $t->status . ($t->due_date ? ' • ' . $t->due_date->format('Y-m-d') : ''),
                'date'  => $t->created_at,
                'id'    => 't' . $t->id,
            ]);
        });

        $case->documents->each(function ($d) use ($events, $case) {
            $events->push([
                'kind'  => 'document',
                'title' => 'مستند — ' . $d->title,
                'sub'   => $d->file_type,
                'date'  => $d->created_at,
                'id'    => 'd' . $d->id,
            ]);
        });

        $sorted = $events->sortByDesc('date')->values();

        return response()->json(['events' => $sorted]);
    }

    public function destroy(LegalCase $case, CaseActivity $activity): JsonResponse
    {
        $this->authorizeCaseActivity($case);

        if ($activity->case_id !== $case->id) {
            return response()->json(['ok' => false, 'message' => 'invalid'], 422);
        }

        $this->logAudit(
            AuditLog::ACTION_DELETE,
            CaseActivity::class,
            $activity->id,
            ['case_id' => $case->id, 'title' => $activity->title],
            null
        );

        $activity->delete();

        return response()->json(['ok' => true]);
    }

    private function authorizeCaseActivity(LegalCase $case): void
    {
        $user = auth()->user();
        abort_unless($user && !$user->isClient(), 403);
    }
}