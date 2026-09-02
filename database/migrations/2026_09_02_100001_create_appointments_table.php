<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مواعيدُ المكتب مع الموكّلين.
 *
 * ═══ لماذا جدولٌ مستقلٌّ لا جلسةٌ أخرى ═══
 *
 * الجلسةُ عند المحكمة موعدٌ فرضته جهةٌ ثالثة على قضية؛ والموعدُ هنا
 * لقاءٌ يقرّره المكتبُ مع شخص. يختلفان في كلّ شيء: الجلسةُ تلزمها
 * قضيةٌ ولا يلزمها محامٍ بعينه، والموعدُ يلزمه شخصٌ ووقتٌ ومن يقابله
 * — وقد لا تكون له قضيةٌ أصلاً (استشارةُ زائرٍ أوّل مرّة).
 *
 * وخلطُهما في جدولٍ واحدٍ كان يعني أعمدةً فارغةً في نصف الصفوف،
 * وتقويماً يخلط ما يُحضَر إليه المحكمة بما يُستقبَل في المكتب.
 *
 * ═══ ولماذا الفهرسان ═══
 *
 * الأوّلُ (الموظّف × الوقت) هو سؤالُ «هل هذه الفُسحة شاغرة؟» — يُسأل
 * مرّةً لكلّ فُسحةٍ تُعرض في شاشة الحجز. والثاني (الوقت × الحالة)
 * سؤالُ التقويم والتذكير: ما القادمُ اليوم وغداً.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('appointments')) {
            return;
        }

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();

            // صاحبُ الموعد: موكّلٌ مسجَّل — أو شخصٌ باسمه ورقمه في
            // أعمدة guest_* (الهجرةُ التالية). أكثرُ المواعيد الأولى
            // مع من لا ملفَّ له بعد، وإلزامُه بملفٍّ كاملٍ قبل أن
            // يُكتب موعدُه يملأ سجلَّ الموكّلين بمن لم يوكّل أحداً.
            $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();

            // القضيةُ اختيارية: استشارةٌ أولى لا قضيةَ لها بعد
            $table->foreignId('case_id')->nullable()->constrained('cases')->nullOnDelete();

            // من يقابله في المكتب — وحذفُ الموظّف لا يمحو الموعد
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title', 190);
            $table->dateTime('starts_at');
            $table->unsignedSmallInteger('minutes')->default(30);
            $table->string('location', 190)->nullable();
            $table->text('notes')->nullable();

            // scheduled | completed | cancelled | no_show
            $table->string('status', 20)->default('scheduled');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // آخرُ تذكيرٍ أُرسل — يمنع تكرارَ التذكير على الموعد نفسِه
            $table->timestamp('reminded_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'starts_at'], 'appointments_user_time_index');
            $table->index(['starts_at', 'status'], 'appointments_time_status_index');
            $table->index(['client_id', 'starts_at'], 'appointments_client_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
