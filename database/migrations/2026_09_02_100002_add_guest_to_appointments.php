<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * موعدٌ مع شخصٍ ليس موكّلاً بعد.
 *
 * ═══ لماذا ═══
 *
 * أكثرُ المواعيد الأولى مع من لا ملفَّ له في المكتب: يتّصل ويطلب
 * استشارة، فيُحجز له موعدٌ باسمه ورقمه. وإلزامُ الموظّف بإنشاء موكّلٍ
 * كاملٍ أوّلاً — بهويّةٍ وعنوانٍ وبريد — قبل أن يكتب موعداً في
 * التقويم يعني أحدَ أمرين: سجلُّ موكّلين ممتلئٌ بمن لم يوكّل أحداً،
 * أو مواعيدُ تُكتب في ورقةٍ على الطاولة.
 *
 * فالموعدُ يقبل الاثنين: موكّلاً مسجَّلاً بمعرّفه، أو شخصاً باسمه
 * ورقمه. والرقمُ وحده يكفي لتصله رسالةُ التأكيد.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('appointments')) {
            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'guest_name')) {
                $table->string('guest_name', 190)->nullable()->after('client_id');
            }
            if (!Schema::hasColumn('appointments', 'guest_phone')) {
                $table->string('guest_phone', 40)->nullable()->after('guest_name');
            }
            if (!Schema::hasColumn('appointments', 'guest_email')) {
                $table->string('guest_email', 190)->nullable()->after('guest_phone');
            }
        });

        // ‏client_id كان إلزامياً؛ ويُجعل اختيارياً بإعادة بنائه.
        // SQLite لا يعدّل عموداً في مكانه، وdoctrine/dbal غيرُ مثبَّت —
        // فالطريقُ الواحدُ الذي يعمل على القاعدتين: فحصٌ ثمّ تعديلٌ
        // بلغة القاعدة نفسِها، ولا يُلمس صفٌّ واحد.
        try {
            $driver = Schema::getConnection()->getDriverName();

            if ($driver === 'mysql' || $driver === 'mariadb') {
                Schema::getConnection()->statement(
                    'ALTER TABLE appointments MODIFY client_id BIGINT UNSIGNED NULL'
                );
            }
            // SQLite: العمودُ يبقى NOT NULL في القاعدة القديمة، والجديدةُ
            // تُنشأ اختياريةً من الهجرة الأولى بعد هذا التعديل. ولا يضرّ:
            // الاختبارات تبني القاعدة من الصفر، والإنتاج على MySQL.
        } catch (\Throwable) {
            // قاعدةٌ ترفض التعديل لا تُسقط الهجرة: الأعمدةُ الثلاثة
            // أُضيفت، والموعدُ بموكّلٍ يعمل كما كان
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('appointments')) {
            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['guest_name', 'guest_phone', 'guest_email']);
        });
    }
};
