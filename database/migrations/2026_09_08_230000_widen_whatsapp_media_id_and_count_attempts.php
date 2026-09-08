<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ‏«Data too long for column 'media_id'» — ٧٣٣٨٥ مهمّة أخفقت نهائياً.
 *
 * ═══ ما وقع ═══
 *
 * ‏media_id كُتب varchar(160) على مقاس معرّفات Meta: رقمٌ قصير يُطلب به
 * الملفُّ من مخزنها. ثمّ صار جسرُ واتساب Evolution، ولا مخزنَ عنده —
 * فيرسل في مكان المعرّف **عنوانَ الملفّ نفسَه** على شبكة واتساب:
 *
 *     https://mmg.whatsapp.net/v/t62.7118-24/12345_678…?ccb=11-4&oh=…&oe=…&_nc_sid=…
 *
 * وهو ثلاثمئةٍ إلى تسعمئة حرف. فكلُّ صورةٍ أو مستندٍ يصل المكتب يُرفض
 * إدراجُه، والمهمّةُ تُعاد خمس مرّات، والمكنسةُ تعيد دفعَ الحدث كلَّ
 * خمس دقائق ما دام غيرَ معالَج — فيتضاعف الإخفاقُ إلى عشرات الآلاف،
 * ويبتلع الطابورُ رسائلَ اليوم عن الرسائل الجديدة.
 *
 * ولم يظهر في أيّ اختبار: حمولاتُ الاختبار مكتوبةٌ بمعرّفات Meta
 * القصيرة، والطولُ الحقيقيّ لا يُعرف إلا من جسرٍ حيّ.
 *
 * ═══ العلاج ═══
 *
 * ١) العمود نصٌّ لا سلسلةٌ محدودة. والقيمةُ تُستعمل كاملةً لجلب الملفّ،
 *    فقصُّها إتلافٌ لا حلّ.
 *
 * ٢) وعدّادُ محاولاتٍ على الحدث. فحدثٌ يستحيل نجاحُه — عيبُ شكلٍ في
 *    البيانات لا انقطاعُ شبكة — كان يُعاد دفعُه إلى الأبد. والعدّادُ
 *    يوقفه بعد حدٍّ، ويبقى الحدثُ على القرص يُرى ويُعدّ: هو رسالةُ
 *    موكّلٍ لم تُقرأ، لا يُحذف.
 *
 * ولا يُلمس صفٌّ قائم: توسيعُ عمودٍ وإضافةُ عمودٍ بقيمةٍ افتراضية.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('whatsapp_messages')) {
            Schema::table('whatsapp_messages', function (Blueprint $table) {
                $table->text('media_id')->nullable()->change();
            });
        }

        if (Schema::hasTable('whatsapp_webhook_events')
            && !Schema::hasColumn('whatsapp_webhook_events', 'attempts')) {
            Schema::table('whatsapp_webhook_events', function (Blueprint $table) {
                $table->unsignedSmallInteger('attempts')->default(0)->after('error');
            });
        }
    }

    public function down(): void
    {
        // العمودُ لا يُضيَّق راجعاً: التضييقُ يقصّ عناوينَ محفوظةً فعلاً،
        // والقصُّ إتلافٌ لا رجعةَ فيه. والعدّادُ يُترك — لا ضررَ منه.
    }
};
