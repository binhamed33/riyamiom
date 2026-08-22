<?php

use App\Models\Client;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * حساب بصمة رقم الهوية للعملاء القائمين.
 *
 * تُنفَّذ مع الترقية فلا يحتاج أي مكتب خطوة يدوية ليعمل دخول عملائه.
 *
 * غير مدمِّرة قطعاً: تقرأ رقم الهوية وتكتب العمود الجديد وحده — لا
 * تحذف صفاً ولا تعدّل حقلاً قائماً ولا تمسّ updated_at. وآمنة للتكرار.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('clients', 'national_id_hash')) {
            return;
        }

        try {
            Client::withTrashed()
                ->whereNull('national_id_hash')
                ->chunkById(200, function ($clients) {
                    foreach ($clients as $client) {
                        $hash = Client::hashNationalId($client->national_id);

                        if ($hash === null) {
                            continue;   // عميل بلا رقم هوية — يبقى كما هو
                        }

                        Client::withTrashed()
                            ->whereKey($client->id)
                            ->update(['national_id_hash' => $hash]);
                    }
                });
        } catch (\Throwable $e) {
            // الترقية لا تسقط بسبب حساب بصمة: الأمر
            // portal:backfill-client-hashes يعيدها متى شئنا
            logger()->warning('backfill client hashes skipped: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        // لا تراجع: العمود نفسه يُحذف في هجرته، ولا بيانات أصلية تغيّرت
    }
};
