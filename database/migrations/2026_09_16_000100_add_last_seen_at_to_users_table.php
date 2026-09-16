<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * آخرُ نشاطٍ بشريٍّ للموظّف — أثرٌ لا يمحوه الخروجُ من النظام.
 *
 * ═══ لماذا لا يكفي جدولُ الجلسات ═══
 *
 * سقفُ المناوبة كان يقرأ «متى رآه الخادم» من sessions.last_activity،
 * وهذا الصفُّ يُحذف لحظةَ الخروج (بالزرّ أو بخمول ساعة) ويُكنَس بعد
 * ساعتين من آخر طلب. فمن عمل إلى الرابعة ثمّ خرج من النظام ضاع أثرُه،
 * وأُقفل سجلُّه على «حضورٌ + السقف» — قبل انصرافه الحقيقيّ بساعات.
 * ثمّ إنّ نبضةَ المزامنة كلَّ ثلاثين ثانية تجدّد الجلسةَ ولو غاب صاحبُها،
 * فـ«رآه الخادم» كانت تعني «تبويبٌ مفتوح» لا «إنسانٌ أمام الشاشة».
 *
 * هذا العمود يكتبه وسيطُ TrackUserActivity من الطلبات البشريّة وحدها
 * (لا نبضات المزامنة ولا الإبقاء على الجلسة)، ويُختم عند الخروج، فيبقى
 * بعد موت الجلسة ويقول الصدق: آخرُ لحظةٍ رأينا فيها الموظّفَ يعمل.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'last_seen_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dateTime('last_seen_at')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'last_seen_at')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('last_seen_at'));
        }
    }
};
