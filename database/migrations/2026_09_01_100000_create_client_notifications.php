<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إشعاراتُ الموكّل — الحدثُ يُقيَّد أوّلاً ثمّ يُرسَل.
 *
 * ═══ لماذا صفٌّ قبل الرسالة ═══
 *
 * لأنّ واتساب قناةُ تنبيهٍ لا مخزنُ بيانات. فالصفُّ هو الإشعارُ نفسه:
 * يظهر في البوابة، ويُقرأ ويُعلَّم مقروءاً، ويبقى إن أخفق الإرسال أو
 * أطفأ المكتبُ واتساب أصلاً. والرسالةُ أثرٌ منه لا العكس.
 *
 * ═══ وevent_key هو الحارس ═══
 *
 * الحدثُ الواحد قد يُطلق مرّتين: حفظٌ مكرّر، أو مهمّةٌ أُعيدت، أو
 * مراقبٌ نُودي من مسارين. ومفتاحٌ فريدٌ مشتقٌّ من الحدث نفسه
 * (نوعُه + معرّفُ موضوعه) يجعل الثانيةَ تسقط عند القاعدة لا في
 * الشيفرة — فسباقُ عمليّتين لا يفلت منه.
 *
 * وخمسُ رسائلَ عن حدثٍ واحد ليست إزعاجاً فحسب: البلاغُ عنها يُنزل
 * تقييمَ جودة رقم المكتب، وقد يُقيَّد إرسالُه كلُّه.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('client_notifications')) {
            Schema::create('client_notifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
                $table->foreignId('case_id')->nullable()->constrained('cases')->nullOnDelete();

                $table->string('type', 40);
                $table->string('title', 190);
                $table->string('body', 500)->nullable();

                // وجهةُ الرابط: أيُّ صفحةٍ في البوابة تُفتح، وعلى أيّ
                // عنصر. تُخزَّن مفكّكةً لا كعنوانٍ جاهز — فالنطاق قد
                // يتغيّر (وقد تغيّر فعلاً) والروابطُ المخزَّنة تموت معه.
                $table->string('target', 40)->default('case');
                $table->unsignedBigInteger('target_id')->nullable();

                $table->string('event_key', 120)->unique();

                $table->timestamp('read_at')->nullable();
                $table->timestamp('notified_at')->nullable();
                $table->string('channel_state', 20)->default('pending');
                $table->string('channel_reason', 190)->nullable();

                $table->timestamps();

                $table->index(['client_id', 'read_at']);
                $table->index(['client_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_notifications');
    }
};
