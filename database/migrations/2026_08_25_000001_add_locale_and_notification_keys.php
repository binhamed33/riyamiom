<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * لغة المستخدم، وإشعارات تُترجَم عند العرض.
 *
 * مشكلتان: اختيار اللغة كان في الجلسة وحدها فيضيع بالخروج، ونصّ
 * الإشعار كان يُكتب حرفياً في القاعدة وقت الإنشاء — بعضه عربي وبعضه
 * إنجليزي — فيرى من اختار العربية إشعارات إنجليزية ولا سبيل لتغييرها.
 *
 * غير مدمِّرة: أعمدة جديدة nullable فقط. الإشعارات القائمة تبقى بنصّها
 * كما هو وتُعرض كما هي — لا يُعدَّل صفّ واحد ولا يُحذف.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'locale')) {
                $table->string('locale', 5)->nullable()->after('appearance');
            }
        });

        Schema::table('notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('notifications', 'title_key')) {
                $table->string('title_key', 120)->nullable()->after('title');
            }
            if (!Schema::hasColumn('notifications', 'message_key')) {
                $table->string('message_key', 120)->nullable()->after('message');
            }
            if (!Schema::hasColumn('notifications', 'params')) {
                $table->json('params')->nullable()->after('message_key');
            }
        });
    }

    public function down(): void
    {
        // لا تراجع: حذف الأعمدة يفقد لغة المستخدمين ومفاتيح إشعاراتهم
    }
};
