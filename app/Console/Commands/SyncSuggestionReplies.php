<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\Suggestion;
use App\Services\PanelReporter;
use App\Support\Notify;
use Illuminate\Console\Command;

/**
 * قناة العودة: يسأل المكتب اللوحة عن مصير اقتراحاته فيكتب الحالة وردّ
 * المطوّر في سجلّ المكتب، ويُشعر صاحب الاقتراح بلغته.
 *
 * لا يُنشئ اقتراحاً ولا يحذف واحداً ولا يمسّ نصّ الموظّف: يكتب حقول
 * الردّ والحالة وحدها، ولا يكتبها إلا إذا اختلفت فعلاً — فتشغيله مرّة
 * أو مئة مرّة سواء، ولا يتكرّر إشعار على ردٍّ واحد.
 *
 * وهو خامد تماماً في مكتب غير مربوط باللوحة.
 */
class SyncSuggestionReplies extends Command
{
    protected $signature = 'suggestions:sync-replies {--limit=200 : أقصى عدد اقتراح يُسأل عنه}';

    protected $description = 'جلب حالة الاقتراحات وردود المطوّر من لوحة مُداوَلة';

    public function handle(): int
    {
        if (!PanelReporter::configured()) {
            $this->line('المكتب غير مربوط بلوحة مُداوَلة — لا شيء يُجلب.');

            return self::SUCCESS;
        }

        $limit = max(1, min(500, (int) $this->option('limit')));

        /** @var \Illuminate\Database\Eloquent\Collection<int, Suggestion> $local */
        $local = Suggestion::query()
            ->latest('id')
            ->limit($limit)
            ->get()
            ->keyBy('id');

        if ($local->isEmpty()) {
            $this->line('لا اقتراحات في هذا المكتب.');

            return self::SUCCESS;
        }

        $replies = PanelReporter::fetchReplies($local->keys()->all());

        if ($replies === []) {
            $this->line('لا جديد من اللوحة.');

            return self::SUCCESS;
        }

        $repliesApplied = 0;
        $statusesApplied = 0;

        foreach ($replies as $row) {
            $suggestion = $local->get((int) ($row['remote_id'] ?? 0));

            if (!$suggestion) {
                continue;
            }

            $changes = [];

            $reply = isset($row['reply']) ? trim((string) $row['reply']) : '';
            $replyIsNew = $reply !== '' && $reply !== trim((string) $suggestion->developer_reply);

            if ($replyIsNew) {
                $changes['developer_reply'] = $reply;
                $changes['replied_at'] = $this->timestamp($row['replied_at'] ?? null);
                $changes['reply_read'] = false;
            }

            $status = (string) ($row['status'] ?? '');
            $becameImplemented = $status === Suggestion::STATUS_IMPLEMENTED
                && $suggestion->status !== Suggestion::STATUS_IMPLEMENTED;

            if (in_array($status, [Suggestion::STATUS_PENDING, Suggestion::STATUS_IMPLEMENTED], true)
                && $status !== $suggestion->status) {
                $changes['status'] = $status;
            }

            // الحالة الدقيقة كما في اللوحة — ليقرأ الموظّف «مخطَّط له»
            // أو «لن يُنفَّذ» بدل «قيد الدراسة» في كل الأحوال
            $panelStatus = (string) ($row['panel_status'] ?? '');

            if (in_array($panelStatus, ['new', 'reviewing', 'planned', 'done', 'declined'], true)
                && $panelStatus !== $suggestion->panel_status) {
                $changes['panel_status'] = $panelStatus;
            }

            if ($changes === []) {
                continue;
            }

            $suggestion->update($changes);

            if ($replyIsNew) {
                $repliesApplied++;
                Notify::send(
                    userId: $suggestion->user_id,
                    titleKey: 'app.notif_suggestion_reply_title',
                    messageKey: 'app.notif_passthrough',
                    params: ['text' => mb_substr($reply, 0, 100)],
                    type: Notification::TYPE_INFO,
                    notifiableType: Suggestion::class,
                    notifiableId: $suggestion->id,
                );
            }

            if ($becameImplemented) {
                $statusesApplied++;
                Notify::send(
                    userId: $suggestion->user_id,
                    titleKey: 'app.notif_suggestion_done_title',
                    messageKey: 'app.notif_suggestion_done_body',
                    params: ['excerpt' => mb_substr((string) $suggestion->content, 0, 60)],
                    type: Notification::TYPE_SUCCESS,
                    notifiableType: Suggestion::class,
                    notifiableId: $suggestion->id,
                );
            }
        }

        $this->info("ردود جديدة: {$repliesApplied} · اقتراحات صارت منفَّذة: {$statusesApplied}");

        return self::SUCCESS;
    }

    private function timestamp(mixed $value): mixed
    {
        if (!is_string($value) || $value === '') {
            return now();
        }

        try {
            return \Illuminate\Support\Carbon::parse($value);
        } catch (\Throwable) {
            return now();
        }
    }
}
