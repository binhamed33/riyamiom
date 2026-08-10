<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CaseActivity;
use App\Models\LegalCase;
use App\Models\Task;
use App\Services\NaturalActionParser;
use App\Traits\AuditLoggable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NaturalActionController extends Controller
{
    use AuditLoggable;

    public function parse(Request $request, NaturalActionParser $parser): JsonResponse
    {
        $this->authorizeTeam();

        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'case_id' => 'nullable|exists:cases,id',
        ]);

        $actions = $parser->parse($validated['message']);

        if (empty($actions)) {
            return response()->json(['ok' => false, 'actions' => [], 'message' => 'لم أستخرج إجراءات من النص.'], 422);
        }

        return response()->json([
            'ok' => true,
            'actions' => $actions,
            'case_id' => $validated['case_id'] ?? null,
        ]);
    }

    public function confirm(Request $request, NaturalActionParser $parser): JsonResponse
    {
        $this->authorizeTeam();

        $data = $request->validate([
            'case_id' => 'nullable|exists:cases,id',
            'actions' => 'required|array|max:5',
            'actions.*.type' => 'required|in:note,call,task,appointment',
            'actions.*.title' => 'required|string|max:255',
            'actions.*.content' => 'nullable|string|max:2000',
            'actions.*.due_date' => 'nullable|date',
        ]);

        $case = !empty($data['case_id']) ? LegalCase::find($data['case_id']) : null;
        if (!empty($data['case_id']) && !$case) {
            return response()->json(['ok' => false, 'message' => 'القضية غير موجودة'], 422);
        }

        $created = [];
        $userId = auth()->id();

        foreach ($data['actions'] as $action) {
            switch ($action['type']) {
                case 'task':
                    $task = Task::create([
                        'title' => $action['title'],
                        'description' => $action['content'] ?? null,
                        'case_id' => $case?->id,
                        'assigned_to' => $userId,
                        'created_by' => $userId,
                        'status' => Task::STATUS_PENDING,
                        'priority' => 'medium',
                        'due_date' => $action['due_date'] ?: null,
                    ]);
                    $this->logAudit(
                        AuditLog::ACTION_CREATE,
                        Task::class,
                        $task->id,
                        null,
                        ['case_id' => $case?->id, 'title' => $task->title]
                    );
                    if ($case) {
                        CaseActivity::create([
                            'case_id' => $case->id,
                            'user_id' => $userId,
                            'type' => CaseActivity::TYPE_TASK,
                            'title' => 'مهمة من الإجراء الصوتي — ' . $task->title,
                            'content' => $task->description,
                            'occurred_at' => now(),
                        ]);
                    }
                    $created[] = ['type' => 'task', 'id' => $task->id];
                    break;

                default:
                    if (!$case) {
                        return response()->json(['ok' => false, 'message' => 'الملاحظات والاتصالات والمواعيد تحتاج قضية مرتبطة.'], 422);
                    }
                    $activity = CaseActivity::create([
                        'case_id' => $case->id,
                        'user_id' => $userId,
                        'type' => $action['type'],
                        'title' => $action['title'],
                        'content' => $action['content'] ?? null,
                        'occurred_at' => $action['due_date'] && in_array($action['type'], ['appointment'])
                            ? $action['due_date'] . ' 09:00:00'
                            : now(),
                    ]);
                    $this->logAudit(
                        AuditLog::ACTION_CREATE,
                        CaseActivity::class,
                        $activity->id,
                        null,
                        ['case_id' => $case->id, 'type' => $activity->type, 'title' => $activity->title]
                    );
                    $created[] = ['type' => $action['type'], 'id' => $activity->id];
                    break;
            }
        }

        return response()->json([
            'ok' => true,
            'created' => $created,
            'case_id' => $case?->id,
            'message' => 'تم إنشاء ' . count($created) . ' إجراء بنجاح',
        ]);
    }

    private function authorizeTeam(): void
    {
        $user = auth()->user();
        abort_unless($user && !$user->isClient(), 403);
    }
}