<?php

namespace App\Jobs;

use App\Models\Suggestion;
use App\Services\PanelReporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * تسليم اقتراح إلى لوحة مُداوَلة.
 *
 * يعمل خارج طلب الموظف: هو كتب اقتراحه وحُفظ عنده، وما بعد ذلك شأن
 * تشغيلي لا ينبغي أن يُبطئه ولا أن يُفشل عمله.
 *
 * وإن تعذّر التسليم لا يضيع الاقتراح: يبقى «معلّقاً» ويُعاد إرساله
 * بمهل متباعدة، ثم يُعلَّم فاشلاً ليلتقطه الأمر الدوري.
 */
class DeliverSuggestionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 30;

    /** مهل متباعدة: عطل شبكة عابر يُصلحه الانتظار لا التكرار الفوري */
    public array $backoff = [30, 300];

    public function __construct(public int $suggestionId)
    {
    }

    public function handle(): void
    {
        $suggestion = Suggestion::find($this->suggestionId);

        if (!$suggestion || $suggestion->delivery_state === 'sent') {
            return;
        }

        // مكتب غير مربوط باللوحة: ليس إخفاقاً، بل لا وجهة أصلاً
        if (!PanelReporter::configured()) {
            $suggestion->forceFill(['delivery_state' => 'skipped', 'delivery_error' => null])->save();

            return;
        }

        $suggestion->increment('delivery_attempts');

        if (PanelReporter::sendSuggestion($suggestion)) {
            $suggestion->forceFill([
                'delivery_state' => 'sent',
                'delivered_at' => now(),
                'delivery_error' => null,
            ])->save();

            return;
        }

        $suggestion->forceFill([
            'delivery_state' => 'pending',
            'delivery_error' => 'تعذّر الوصول إلى لوحة مُداوَلة',
        ])->save();

        throw new \RuntimeException('suggestion delivery failed');
    }

    /** بعد استنفاد المحاولات: يُعلَّم فاشلاً ليلتقطه الأمر الدوري */
    public function failed(\Throwable $e): void
    {
        Suggestion::whereKey($this->suggestionId)->update([
            'delivery_state' => 'failed',
            'delivery_error' => mb_substr($e->getMessage(), 0, 300),
        ]);
    }
}
