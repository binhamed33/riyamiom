<?php

namespace App\Logging;

use App\Support\MailIdentity;
use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;

/**
 * لا يخرج سرٌّ إلى ملفّ سجلّ، أياً كان من كتبه.
 *
 * ═══ لماذا عند المُنسِّق لا عند من يكتب ═══
 *
 * كانت التنقية موزّعةً على مواضع النداء: OfficeMailer ينقّي، وOfficeMail
 * ينقّي، والمُبلِّغ العام في bootstrap/app.php ينقّي. وكلُّها صحيحة
 * وكلُّها لا تكفي — لأنّ لارافل يسجّل الاستثناء مرّةً ثانية بمُبلِّغه
 * الافتراضي، فيُكتب السطرُ خاماً بجانب المنقّى.
 *
 * ثبتَ ذلك بالتجربة: بلاغٌ واحد أنتج سطرين، أحدهما فيه اسم المستخدم
 * كما ردّه Gmail.
 *
 * وحتى لو نُقّيت الرسالة، يبقى الاستثناء نفسه في سياق السجلّ، ويُنسّقه
 * Monolog بصنفه ورسالته وأثره — فيعود السرُّ من باب السياق.
 *
 * فالحراسةُ هنا: آخرُ نقطةٍ قبل أن تصير السطورُ بايتات. ما يكتبه أيُّ
 * كودٍ في أيّ موضع — رسالةً كان أو سياقاً أو أثراً — يمرّ من هنا.
 *
 * ولا يُلغي ذلك التنقيةَ في مواضع النداء: تلك تحمي ما يُقرأ برمجياً
 * قبل التنسيق، وهذه تحمي الملفّ. والسرُّ لا يُحرس بطبقةٍ واحدة.
 */
class ScrubSecrets
{
    public function __invoke(\Illuminate\Log\Logger $logger): void
    {
        foreach ($logger->getLogger()->getHandlers() as $handler) {
            if (!method_exists($handler, 'getFormatter') || !method_exists($handler, 'setFormatter')) {
                continue;
            }

            $formatter = $handler->getFormatter();

            // لا يُلفّ مرّتين: التنبيت قد يُنادى على معالجٍ مشترك
            if ($formatter instanceof ScrubbingFormatter) {
                continue;
            }

            $handler->setFormatter(new ScrubbingFormatter($formatter));
        }
    }
}

/**
 * يُنسّق كما يُنسّق الأصل، ثم يمرّ على الناتج فيحجب ما يجب حجبه.
 */
class ScrubbingFormatter implements FormatterInterface
{
    public function __construct(private readonly FormatterInterface $inner)
    {
    }

    public function format(LogRecord $record): mixed
    {
        return $this->clean($this->inner->format($record));
    }

    public function formatBatch(array $records): mixed
    {
        return $this->clean($this->inner->formatBatch($records));
    }

    /**
     * النصُّ وحده يُنقّى؛ وما ليس نصّاً يُترك كما هو.
     *
     * بعض المُنسِّقات تُرجع مصفوفةً (JSON مثلاً) لا سلسلة، وتحويلُها
     * قسراً يكسر المعالج الذي ينتظرها.
     */
    private function clean(mixed $formatted): mixed
    {
        if (is_string($formatted)) {
            return MailIdentity::scrub($formatted);
        }

        if (is_array($formatted)) {
            return array_map(fn ($v) => is_string($v) ? MailIdentity::scrub($v) : $v, $formatted);
        }

        return $formatted;
    }
}
