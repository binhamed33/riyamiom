<?php

namespace App\Jobs;

use App\Http\Controllers\AssistantController;
use App\Models\AssistantMessage;
use App\Services\GeminiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * يُجيب سؤالاً تعذّر جوابه لحظةَ سؤاله.
 *
 * ═══ الحدّ الذي لا يتجاوزه كودٌ مهما احتاط ═══
 *
 * إعادةُ المحاولة تنفع على تعثّرٍ عابر. أمّا انقطاعُ خدمة المزوّد أو
 * نفادُ الحصّة فلا تُصلحه إعادةٌ في ثوانٍ — ولا شيء يُنتج جواباً وقتها.
 * والذي يملكه النظام أن يحفظ السؤال ويعود إليه من نفسه حين تعود
 * الخدمة، بدل أن يقول «حاول لاحقاً» ويترك التذكّر على المحامي.
 *
 * الفواصل تتّسع: دقيقة، ثمّ خمس، ثمّ ربع ساعة، ثمّ نصفها. وانقطاعٌ
 * يجاوز ستّ ساعات يُترك — فجوابٌ يصل بعد يومٍ لا يُنتظر.
 */
class AnswerAssistantQuestion implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(
        public int $userId,
        public int $questionId,
    ) {
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(6);
    }

    public function handle(): void
    {
        $question = AssistantMessage::where('id', $this->questionId)
            ->where('user_id', $this->userId)
            ->where('role', 'user')
            ->first();

        // مُسحت المحادثة أو حُذف السؤال — لا يُبعث من قبره
        if (!$question) {
            return;
        }

        // أُجيب بالفعل: أعاد المحامي الإرسال بنفسه فنجح، أو سبقت مهمّةٌ
        // أخرى. جوابان على سؤالٍ واحد أسوأ من جوابٍ متأخّر.
        $answered = AssistantMessage::where('user_id', $this->userId)
            ->where('role', 'assistant')
            ->where('id', '>', $question->id)
            ->exists();

        if ($answered) {
            return;
        }

        $service = new GeminiService();
        if (!$service->isConfigured()) {
            return;   // لا مفتاح للمكتب — لا تُصلحه إعادة
        }

        $reply = $service->chat(
            AssistantMessage::contextUpTo($this->userId, $question->id),
            AssistantController::buildSystemPrompt($question->content),
        );

        if (!$reply) {
            throw new \RuntimeException('لم يُرجع المزوّد جواباً — تُعاد المحاولة.');
        }

        AssistantMessage::write($this->userId, 'assistant', $this->label($question) . $reply);
    }

    /**
     * ترويسةٌ تُوضح أنّ هذا جوابُ سؤالٍ سابق.
     *
     * الجواب المتأخّر يُضاف آخر المحادثة، وقد كتب المحامي بعده أسئلةً
     * أخرى — فيقرأ جواباً تحت سؤالٍ ليس سؤاله. والسطر يزيل اللبس.
     */
    private function label(AssistantMessage $question): string
    {
        $newer = AssistantMessage::where('user_id', $this->userId)
            ->where('role', 'user')
            ->where('id', '>', $question->id)
            ->exists();

        if (!$newer) {
            return '';
        }

        return '↩︎ جواب سؤالك السابق: «'
            . \Illuminate\Support\Str::limit($question->content, 60)
            . "»\n\n";
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('تعذّر جواب سؤال المساعد #' . $this->questionId . ' — ' . $e->getMessage());
    }
}
