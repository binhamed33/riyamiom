<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * مَن كتب وقتَ الانصراف — كي يُجاب «ما الذي أخرجني ١:٣٦؟».
 *
 * محامٍ وجد انصرافَه مسجَّلاً ١:٣٦ وهو خرج ٣:٥٧، ولا أحد يستطيع أن يقول
 * من الصفّ وحده: أزرُّ الانصراف؟ أم السقف؟ أم الإقفالُ الليليّ؟ أم زرُّ
 * الخروج القديم؟ كان `source` يحمل أصلَ الإنشاء (دخولٌ أو زرّ) ثمّ يُكتب
 * فوقه عند الإقفال الآليّ، فضاع الاثنان معاً.
 *
 * closed_by يحمل الكاتبَ وحده: button (زرّ الانصراف)، cap (السقف)،
 * seen (آخرُ نشاطٍ مرئيّ — ومنه الإقفالُ الليليّ)، manager (تصحيحُ الإداري)،
 * logout (زرُّ الخروج القديم)، legacy (صفوفٌ أُقفلت قبل هذا العمود ولا
 * يُعرف كاتبُها). والسجلّاتُ القائمة تُملأ من واقع `source` ما أمكن —
 * بثوابت النموذج لا بنصوصٍ حرّة: قيمةٌ لا يعرفها النموذج تُفقد الصفَّ وسمَه.
 *
 * وinferred_minutes: كم دقيقةً من مجموع اليوم كتبها النظامُ لا صاحبُها.
 * فترةٌ صباحيّة بلغت السقفَ ثمّ استُؤنف اليومُ وأُقفل بالزرّ كانت تفقد
 * وسمَ السقف كلَّه — والثماني المستنتَجةُ باقيةٌ في المجموع بلا علامة.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_attendance')) {
            return;
        }

        Schema::table('hr_attendance', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_attendance', 'closed_by')) {
                $table->string('closed_by', 20)->nullable()->after('status');
            }
            if (! Schema::hasColumn('hr_attendance', 'inferred_minutes')) {
                $table->unsignedInteger('inferred_minutes')->default(0)->after('minutes');
            }
        });

        // لا يُمسّ إلا ما أُقفل ولم يُعرف كاتبُه بعد
        DB::table('hr_attendance')->whereNotNull('check_out_at')->whereNull('closed_by')
            ->where('source', 'auto_capped')->update(['closed_by' => \App\Models\HrAttendance::CLOSED_BY_CAP]);
        DB::table('hr_attendance')->whereNotNull('check_out_at')->whereNull('closed_by')
            ->where('source', 'auto_closed')->update(['closed_by' => \App\Models\HrAttendance::CLOSED_BY_SEEN]);
        // زرُّ الخروج القديم كان يكتب الانصرافَ وسطرَ التدقيق في الطلب نفسِه —
        // في الثواني نفسِها. فانصرافٌ يتبعه خروجٌ في خمس ثوانٍ كتبه الزرُّ القديم
        // لا صاحبُه؛ أمّا زرُّ انصرافٍ ثمّ خروجٌ بعد دقيقة فهذا تسلسلُ نهاية
        // يومٍ عاديّ يبقى «غير موثَّق». وسمٌ لا تغييرَ وقت — ليُقرأ «١:٣٦» على
        // حقيقته. وchunkById لا chunk: التحديثُ يُخرج الصفَّ من شرط الاستعلام
        // فيقفز الإزاحةُ فوق نصف الصفوف.
        if (Schema::hasTable('audit_logs')) {
            DB::table('hr_attendance')->whereNotNull('check_out_at')->whereNull('closed_by')
                ->orderBy('id')->chunkById(200, function ($rows) {
                    foreach ($rows as $row) {
                        $out = \Carbon\Carbon::parse($row->check_out_at);
                        $logout = DB::table('audit_logs')->where('user_id', $row->user_id)->where('action', 'logout')
                            ->whereBetween('created_at', [$out->copy(), $out->copy()->addSeconds(5)])
                            ->exists();

                        if ($logout) {
                            DB::table('hr_attendance')->where('id', $row->id)->update(['closed_by' => \App\Models\HrAttendance::CLOSED_BY_LOGOUT]);
                        }
                    }
                });
        }

        DB::table('hr_attendance')->whereNotNull('check_out_at')->whereNull('closed_by')
            ->update(['closed_by' => \App\Models\HrAttendance::CLOSED_BY_LEGACY]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('hr_attendance')) {
            return;
        }

        Schema::table('hr_attendance', function (Blueprint $table) {
            foreach (['closed_by', 'inferred_minutes'] as $col) {
                if (Schema::hasColumn('hr_attendance', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
