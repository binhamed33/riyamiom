<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Services\AppointmentNotifier;
use App\Support\AppointmentSlots;
use App\Support\ClientEvents;
use Illuminate\Console\Command;

/**
 * تذكيرُ الموكّلين بمواعيدهم القادمة.
 *
 * ═══ لماذا نافذةٌ لا لحظة ═══
 *
 * الأمرُ يعمل كلَّ ساعة، فلو بحث عن موعدٍ يبعد «أربعاً وعشرين ساعةً
 * بالضبط» لما وجد شيئاً أبداً إلا مصادفة. فالنافذةُ من الآن إلى حدّ
 * التذكير: كلُّ ما دخل المدى ولم يُذكَّر به بعد.
 *
 * و`reminded_at` يمنع التكرار: ساعةٌ بعد ساعةٍ لا تُعيد الرسالةَ
 * نفسَها، ولو بقي الموعدُ داخل النافذة عشرين ساعة.
 */
class RemindAppointments extends Command
{
    protected $signature = 'appointments:remind {--dry : عرضٌ بلا إرسال}';

    protected $description = 'يذكّر الموكّلين بمواعيدهم القادمة قبلها بالمدّة المضبوطة';

    public function handle(): int
    {
        if (!ClientEvents::enabled(ClientEvents::APPOINTMENT_REMINDER)) {
            $this->info('تذكيرُ المواعيد مُطفأ في إعدادات المكتب — لا شيء يُرسَل.');

            return self::SUCCESS;
        }

        $hours = AppointmentSlots::remindHours();

        $due = Appointment::query()
            ->where('status', Appointment::STATUS_SCHEDULED)
            ->whereNull('reminded_at')
            ->whereBetween('starts_at', [now(), now()->addHours($hours)])
            ->with(['client', 'case', 'user'])
            ->orderBy('starts_at')
            ->get();

        if ($due->isEmpty()) {
            $this->info('لا مواعيد تستحقّ تذكيراً الآن.');

            return self::SUCCESS;
        }

        foreach ($due as $appointment) {
            $this->line('  · ' . $appointment->whenText() . ' — ' . ($appointment->client?->name ?? '؟'));

            if ($this->option('dry')) {
                continue;
            }

            AppointmentNotifier::reminder($appointment);

            // يُختم قبل الإرسال أم بعده؟ بعده: رسالةٌ أخفقت لأنّ
            // الشبكةَ تعثّرت تستحقّ محاولةً في الساعة القادمة، ووسمُها
            // مسبقاً كان يبتلعها إلى الأبد.
            $appointment->forceFill(['reminded_at' => now()])->save();
        }

        $this->info($this->option('dry')
            ? 'عرضٌ بلا إرسال — ' . $due->count() . ' موعداً يستحقّ التذكير.'
            : 'ذُكِّر ' . $due->count() . ' موعداً.');

        return self::SUCCESS;
    }
}
