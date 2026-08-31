<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * روابطُ الدخول المرسَلة في واتساب.
 *
 * ═══ لماذا تُخزَّن بصمتُها لا هي ═══
 *
 * الرابطُ يفتح ملفَّ قضيةٍ بلا كلمةِ مرور. فلو خُزّن نصّاً صريحاً،
 * لكانت نسخةٌ من قاعدة البيانات — نسخةٌ احتياطية مسرَّبة، أو قرصٌ
 * مُهمَل — مفاتيحَ جاهزةً لملفّات كلّ الموكّلين. فتُخزَّن بصمةٌ
 * تُطابَق ولا تُعكَس، ويعيش الرابطُ في رسالة الموكّل وحدها.
 *
 * ═══ وثلاثةُ حدود ═══
 *
 * مدّةٌ تنتهي: رسالةُ واتساب تبقى في الهاتف سنين، وهاتفٌ يُباع أو
 * يُسرَق بعد عام لا يجوز أن يفتح ملفّاً.
 * واستعمالٌ واحد: الرابطُ المُعاد توجيهُه أو المنسوخُ في مجموعةٍ لا
 * يعمل مرّتين.
 * وإبطالٌ صريح: يُلغى ما أُرسل بالخطأ قبل أن يُفتح.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('client_portal_links')) {
            Schema::create('client_portal_links', function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
                $table->foreignId('notification_id')->nullable()
                    ->constrained('client_notifications')->nullOnDelete();

                $table->string('token_hash', 64)->unique();
                $table->string('target', 40)->default('case');
                $table->unsignedBigInteger('target_id')->nullable();

                $table->timestamp('expires_at');
                $table->timestamp('used_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->string('used_ip', 45)->nullable();

                $table->timestamps();

                $table->index(['client_id', 'expires_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_portal_links');
    }
};
