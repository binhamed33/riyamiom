<?php

use App\Models\Client;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * بصمةُ هاتف الموكّل — ليُعرَف صاحبُ الرسالة الواردة.
 *
 * ═══ العطل الذي تمنعه ═══
 *
 * هاتف الموكّل مشفَّر في قاعدة البيانات (سمة Encryptable)، والتشفير
 * غيرُ حتميّ: نفس الرقم يُكتب في كل حفظٍ نصّاً مختلفاً. فالبحث
 * ‏`where('phone', $incoming)` لا يُطابق شيئاً أبداً، والبديلُ فكُّ
 * تشفير كلّ الموكّلين عند كلّ رسالةٍ واردة — بطءٌ وتسريبٌ للنصّ
 * الصريح في الذاكرة بلا داعٍ.
 *
 * فبصمةٌ حتميّة مفتاحُها مفتاحُ التطبيق، على منوال national_id_hash
 * الموجود: تُطابَق ولا تُعكَس، وتخصّ هذا المكتب وحده — لا تُقارَن
 * ببصمة مكتبٍ آخر ولو سُرّبت.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('clients', 'phone_hash')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->string('phone_hash', 64)->nullable()->index()->after('phone');
            });
        }

        // ملء البصمات للموكّلين القائمين — قراءةٌ وتحديثُ عمودٍ جديد
        // وحده. لا يُمسّ رقمٌ ولا يُحذف صفّ، والفشلُ في صفٍّ واحد لا
        // يُسقط الهجرة كلَّها فيترك المكتب بعمودٍ نصفِ ممتلئ.
        Client::withTrashed()->chunkById(200, function ($clients) {
            foreach ($clients as $client) {
                try {
                    $hash = Client::hashPhone($client->phone);

                    if ($hash !== null) {
                        DB::table('clients')->where('id', $client->id)->update(['phone_hash' => $hash]);
                    }
                } catch (\Throwable) {
                    // صفٌّ لا يُفكّ تشفيره (مفتاح تطبيق قديم) — يبقى بلا
                    // بصمة ويُربط يدوياً، ولا يمنع بقيّة المكتب
                }
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('clients', 'phone_hash')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropColumn('phone_hash');
            });
        }
    }
};
