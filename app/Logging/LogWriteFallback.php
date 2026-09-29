<?php

namespace App\Logging;

use Monolog\LogRecord;

/**
 * سجلٌّ لا يُكتب لا يُسقط الصفحة.
 *
 * ═══ ما وقع ═══
 *
 * امتلأ قرصُ الخادم. فأوّلُ خطأٍ في أيّ صفحة — قالبٌ لا يُجمَّع لأنّ
 * storage/framework/views لا يقبل كتابة — يمضي إلى معالج الاستثناءات
 * ليُدوَّن ثمّ يُعرض برسالةٍ عربيّة. والتدوينُ نفسُه يسقط: Monolog
 * يرمي UnexpectedValueException «Writing to the log file failed …
 * No space left on device» من داخل المُبلِّغ، فيخرج الاستثناءُ من
 * معالج الاستثناءات نفسِه، وPHP لا يجد من يعالجه، فيُغلق الردَّ بـ500
 * وجسمٍ فارغ: «This page isn't working».
 *
 * وهكذا صار عطلٌ في القرص صفحةً بيضاءَ على أربعَ عشرةَ صفحةً في مكتب
 * الوالد — بلا رسالةٍ ولا رمزِ مرجعٍ ولا سطرٍ في السجلّ يقول لماذا،
 * لأنّ السجلَّ هو ما لا يُكتب. وأُعيد إنتاجُه محلّياً بقرصٍ ممتلئ:
 * الحالةُ 500 والجسمُ صفرُ بايت.
 *
 * ═══ وما هنا ═══
 *
 * Monolog يُتيح معالجاً لما يرميه أحدُ مُخرِجاته. فحين تخيب الكتابةُ
 * إلى الملفّ يُكتب سطرٌ واحد بـerror_log — قناةُ PHP نفسِها، التي تصل
 * سجلَّ PHP-FPM ولا تمرّ بـstorage/ — ويمضي الطلب. فيرى المستخدمُ رسالةَ
 * الخطأ العربيّة كما لو كان السجلّ يعمل، ويجد مديرُ الخادم في سجلّ PHP
 * سبباً صريحاً: «No space left on device» أو «Permission denied».
 *
 * ومرّةً في الطلب لكلّ سبب لا مع كلّ سطر: طلبٌ يخيب فيه السجلُّ يخيب
 * في كلّ سطرٍ يحاوله، فلا يُغرَق سجلُّ PHP بالسطر نفسِه.
 *
 * ولا يُبتلع صامتاً: ‎ignore_exceptions‎ في قناة stack يُسكت الخيبةَ
 * بلا أثر، فيبقى القرصُ ممتلئاً وما من سطرٍ في أيّ مكانٍ يقول ذلك.
 *
 * ويُثبَّت على stack نفسِها لا على single وحدها: stack تستدعي مُخرِجاتِ
 * قنواتها مباشرةً، فمعالجُ single لا يُسأل حين يُكتب من stack.
 */
class LogWriteFallback
{
    public function __invoke(\Illuminate\Log\Logger $logger): void
    {
        $monolog = $logger->getLogger();

        if (!$monolog instanceof \Monolog\Logger) {
            return;
        }

        $said = [];

        $monolog->setExceptionHandler(static function (\Throwable $e, LogRecord $record) use (&$said): void {
            // السطرُ الأوّل وحدَه: Monolog يُلحق بالرسالة «The exception
            // occurred while attempting to log: …» ثمّ نصَّ السطر الذي
            // كان سيُكتب — وذاك قد يحمل اسمَ موكّل، وسجلُّ PHP ليس مكانَه
            $reason = strtok($e->getMessage(), "\n") ?: get_class($e);

            // «Write of 812 bytes failed» — العددُ يختلف مع كلّ سطر، والسببُ
            // واحد؛ فيُقارَن بلا أرقام وإلا كُتب سطرٌ لكلّ محاولة
            $key = preg_replace('/\d+/', 'N', $reason);

            if (isset($said[$key])) {
                return;
            }

            $said[$key] = true;

            // بلا اسم القناة: لارافل يسمّي مسجّلات Monolog باسم البيئة
            // (production) لا باسم القناة، فلا يقول الاسمُ شيئاً
            error_log('[mudawala] log write failed: ' . $reason);
        });
    }
}
