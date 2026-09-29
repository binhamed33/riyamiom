<?php

namespace App\Logging;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * ملفُّ سجلٍّ له سقف — لا يستطيع مكتبٌ واحد أن يبتلع القرص.
 *
 * ═══ ما وقع ═══
 *
 * سجلُّ مكتبِ اختبارٍ واحد بلغ 47 غيغابايت: حلقةُ إخفاقٍ في طابور
 * واتساب كتبت أثرَها ملايينَ المرّات في أسبوع، ثمّ أُصلحت الحلقة —
 * وبقي الملفّ. وقناةُ single في لارافل ملفٌّ واحدٌ يكبر إلى الأبد،
 * فامتلأ القرصُ بعد أسابيع وسقطت صفحاتُ **كلّ** المكاتب بيضاء، ومكتبُ
 * الوالد أوّلُها، بسبب سجلِّ مكتبٍ لا علاقةَ له به.
 *
 * ═══ لماذا بالحجم لا باليوم ═══
 *
 * قناةُ daily تُدوّر بالتاريخ: عاصفةٌ تكتب ثلاثةَ غيغابايت في اليوم
 * تُبقي أربعةَ عشرَ ملفّاً بأربعين غيغابايت — وهو المرضُ نفسُه بأسماء
 * ملفّاتٍ أكثر. السقفُ الوحيد الذي يعني شيئاً على قرصٍ هو البايتات.
 *
 * ═══ وكيف ═══
 *
 * قبل كلّ كتابةٍ يُقاس الملفّ؛ فإن بلغ السقفَ أُزيحت الأجيالُ
 * (‎laravel.log.1‎ ← ‎laravel.log‎، ‎.2‎ ← ‎.1‎، والأقدمُ يسقط) وفُتح
 * ملفٌّ جديد. الاسمُ الحاليُّ ثابت — ‎laravel.log‎ — فمن يقرؤه
 * (office:health، نبضةُ الأخطاء، office:errors) لا يتغيّر.
 *
 * وrename ذرّيّة: عاملُ PHP آخرُ يحمل الملفَّ القديمَ مفتوحاً يُكمل
 * فيه ثمّ يفتح الجديدَ في طلبه التالي. وإن تسابق عاملان على الإزاحة
 * خسر أحدُهما rename على ملفٍّ زال — فيُهمَل، ولا يُرمى.
 *
 * والاسمُ في .env يبقى «single»: ما يُشير إليه ‎LOG_STACK‎ في كلّ
 * مكتبٍ هو اسمُ القناة لا سائقُها، فلا يُلمس ملفُّ بيئةٍ لينتشر السقف.
 */
class CappedFileHandler extends StreamHandler
{
    public function __construct(
        string $stream,
        private readonly int $maxBytes = 100 * 1024 * 1024,
        private readonly int $generations = 2,
        int|string|Level $level = Level::Debug,
        bool $bubble = true,
        ?int $filePermission = null,
        bool $useLocking = false,
    ) {
        parent::__construct($stream, $level, $bubble, $filePermission, $useLocking);
    }

    protected function write(LogRecord $record): void
    {
        $this->rotateIfFull();

        parent::write($record);
    }

    private function rotateIfFull(): void
    {
        $url = $this->getUrl();

        if ($url === null || $this->maxBytes <= 0) {
            return;
        }

        clearstatcache(true, $url);
        $size = @filesize($url);

        if ($size === false || $size < $this->maxBytes) {
            return;
        }

        // يُغلق المقبضُ أوّلاً: الكتابةُ التالية تفتح الاسمَ من جديد
        // فتقع في ملفٍّ فارغ لا في الذي أُزيح
        $this->close();

        for ($i = $this->generations; $i >= 1; $i--) {
            $older = $url . '.' . $i;
            $newer = $i === 1 ? $url : $url . '.' . ($i - 1);

            if ($i === $this->generations && file_exists($older)) {
                @unlink($older);
            }

            if (file_exists($newer)) {
                @rename($newer, $older);
            }
        }

        if ($this->generations === 0) {
            @unlink($url);
        }
    }
}
