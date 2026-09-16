<?php

namespace App\Console\Commands;

use App\Models\HrAttendance;
use App\Models\Setting;
use App\Models\User;
use App\Support\AttendanceGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * قراءةُ سجلّ حضور موظّف كما هو — ليُجاب «ما الذي أخرجني ١:٣٦؟».
 *
 * قراءةٌ فقط: لا يكتب ولا يقفل ولا يصحّح. يطبع الإعداداتِ التي تحكم
 * الإقفال (السقف، الإقفال الليليّ، الحضور التلقائيّ) ثمّ صفوفَ الأيّام
 * بكاتب كلّ انصراف وأثرِ ملاحظته — فيُعرف من الطرفيّة ما لا يقوله
 * الكشف، بلا فتح قاعدة البيانات بالعين.
 */
class AttendanceLog extends Command
{
    protected $signature = 'hr:attendance-log
        {--employee= : رقمُ الموظّف أو بريدُه — الجميع افتراضاً}
        {--days=14 : كم يوماً إلى الوراء}';

    protected $description = 'سجلّ الحضور بكاتب كلّ انصراف — قراءةٌ فقط';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));

        $this->line('');
        $this->line('  الإعداداتُ الحاكمة: سقفُ اليوم ' . AttendanceGuard::capHours() . ' ساعات'
            . ' · الإقفالُ الليليّ ' . (AttendanceGuard::autoCloseEnabled() ? 'مفعَّل' : 'معطَّل')
            . ' · الحضورُ التلقائيّ ' . (AttendanceGuard::autoEnabled() ? 'مفعَّل' : 'معطَّل')
            . ' · محرّكُ الجلسات ' . config('session.driver'));

        $query = HrAttendance::with('user')
            ->whereDate('work_date', '>=', now('Asia/Muscat')->subDays($days)->toDateString())
            ->orderBy('work_date')->orderBy('user_id');

        if ($this->option('employee')) {
            $needle = (string) $this->option('employee');
            $user = ctype_digit($needle) ? User::find((int) $needle) : User::where('email', $needle)->first();

            if (! $user) {
                $this->error('لا موظّفَ بهذا الرقم أو البريد: ' . $needle);

                return self::FAILURE;
            }

            $query->where('user_id', $user->id);

            $seen = Schema::hasColumn('users', 'last_seen_at')
                ? DB::table('users')->where('id', $user->id)->value('last_seen_at')
                : null;
            $this->line('  الموظّف: #' . $user->id . ' ' . $user->name . ' · آخرُ نشاطٍ بشريّ: ' . ($seen ?: '— (لم يُختم بعد)'));
        }

        $rows = $query->get();

        if ($rows->isEmpty()) {
            $this->info('  لا سجلّات في آخر ' . $days . ' يوماً.');

            return self::SUCCESS;
        }

        $tz = 'Asia/Muscat';
        $this->line('');
        $this->table(
            ['اليوم', 'الموظّف', 'الحضور', 'الانصراف', 'المدّة', 'فترات', 'الكاتب', 'المصدر', 'الأثر'],
            $rows->map(fn ($r) => [
                $r->work_date->toDateString(),
                '#' . $r->user_id . ' ' . ($r->user->name ?? '—'),
                $r->check_in_at->timezone($tz)->format('H:i'),
                $r->checkOutDisplay() ?? '— مفتوح',
                $r->minutes === null ? '—' : \App\Support\Duration::human((int) $r->minutes),
                (int) ($r->intervals ?: 1),
                $r->closed_by ?? ($r->check_out_at ? 'legacy' : '—'),
                $r->source,
                mb_substr((string) $r->note, 0, 90),
            ])->all(),
        );

        $this->line('  الكاتب: button = زرُّ الانصراف · cap = سقفُ اليوم · seen = آخرُ نشاط · manager = تصحيحُ الإداري · legacy = قبل التوثيق');
        $this->line('');

        return self::SUCCESS;
    }
}
