<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إشعار لا يشير إلى سجل بعينه — كإشعار عام — يجب أن يُحفظ.
 *
 * تعديل عمود قائم (change) أثقل من إضافة عمود: يعيد MySQL بناء تعريفه،
 * وقد يفشل إن كان العمود ضمن فهرس مركّب أو مختلف النوع عمّا نفترض.
 * وفشله يُسقط التحديث كلّه ويستعيد محرّك النشر النسخة الاحتياطية.
 *
 * فنتحقّق أولاً: الجدول موجود، والعمودان موجودان، وليسا nullable
 * أصلاً. وإن كانا كذلك فلا شيء نفعله — وتشغيل الهجرة مرّة أو مئة سواء.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('notifications')) {
            return;
        }

        $targets = array_filter(
            ['notifiable_type', 'notifiable_id'],
            fn (string $c) => Schema::hasColumn('notifications', $c) && !$this->isNullable($c),
        );

        if ($targets === []) {
            return;
        }

        Schema::table('notifications', function (Blueprint $table) use ($targets) {
            if (in_array('notifiable_type', $targets, true)) {
                $table->string('notifiable_type')->nullable()->change();
            }

            if (in_array('notifiable_id', $targets, true)) {
                $table->unsignedBigInteger('notifiable_id')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        // لا نُعيدهما إلزاميين: صفوف بقيمة فارغة موجودة الآن
    }

    private function isNullable(string $column): bool
    {
        try {
            foreach (Schema::getColumns('notifications') as $definition) {
                if (($definition['name'] ?? null) === $column) {
                    return (bool) ($definition['nullable'] ?? false);
                }
            }
        } catch (\Throwable) {
            // تعذّر الفحص: نمضي في التعديل كما كان
        }

        return false;
    }
};
