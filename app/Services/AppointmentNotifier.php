<?php

namespace App\Services;

use App\Mail\ClientEventMail;
use App\Mail\MailKind;
use App\Models\Appointment;
use App\Services\ClientPortal\ClientNotifications;
use App\Support\ClientEvents;
use Illuminate\Support\Facades\Log;

/**
 * إخبارُ الموكّل بموعده — واتساباً وبريداً من موضعٍ واحد.
 *
 * ═══ لماذا صنفٌ واحدٌ لا سطران في المتحكّم ═══
 *
 * لأنّ للحدث الواحد قناتين وأربعَ حالات (حجزٌ، تغييرٌ، إلغاءٌ،
 * تذكير)، ونصَّ الرسالة يجب أن يكون واحداً في القناتين: موكّلٌ يقرأ
 * في الواتساب «التاسعة» وفي البريد «9:00» يظنّهما موعدين.
 *
 * وتكرارُ ذلك في المتحكّم وأمرِ التذكير يعني أنّ أحدَهما سينسى
 * قناةً أو يكتب صيغةً أخرى.
 *
 * ═══ ولا يُفشِل الحجزَ إخفاقُ رسالة ═══
 *
 * الموعدُ محفوظٌ في المكتب سواءٌ وصلت رسالتُه أم لا. فكلُّ إرسالٍ
 * هنا داخل try، وأثرُ الإخفاق في السجلّ لا في وجه الموظّف.
 */
class AppointmentNotifier
{
    public static function created(Appointment $appointment): void
    {
        self::send($appointment, ClientEvents::APPOINTMENT_NEW, 'تأكيد موعد', 'نؤكّد لكم حجزَ موعدٍ في المكتب.');
    }

    public static function moved(Appointment $appointment): void
    {
        self::send($appointment, ClientEvents::APPOINTMENT_MOVED, 'تغيير موعد', 'نُفيدكم بتغيير موعدكم لدى المكتب إلى الآتي.');
    }

    public static function cancelled(Appointment $appointment): void
    {
        self::send($appointment, ClientEvents::APPOINTMENT_CANCELLED, 'إلغاء موعد', 'نُفيدكم بإلغاء الموعد الآتي. للحجز من جديد يُرجى التواصل مع المكتب.');
    }

    public static function reminder(Appointment $appointment): void
    {
        self::send($appointment, ClientEvents::APPOINTMENT_REMINDER, 'تذكير بموعد', 'نذكّركم بموعدكم القادم لدى المكتب.');
    }

    /**
     * نصٌّ واحدٌ للقناتين، ومفتاحُ حدثٍ يمنع التكرار.
     *
     * مفتاحُ الحدث يحمل وقتَ الموعد لا رقمَه وحده: موعدٌ أُجّل ثمّ
     * أُعيد إلى وقته الأوّل حدثٌ جديدٌ يستحقّ رسالةً، والمفتاحُ
     * بالرقم وحده كان يبتلعها صامتاً.
     */
    private static function send(Appointment $appointment, string $type, string $heading, string $lead): void
    {
        try {
            $appointment->loadMissing(['client', 'case', 'user']);
            $client = $appointment->client;

            if (!$client) {
                return;
            }

            $lines = array_filter([
                $lead,
                'الموضوع: ' . $appointment->title,
                'الموعد: ' . $appointment->whenText(),
                $appointment->location ? 'المكان: ' . $appointment->location : null,
                $appointment->user ? 'مع: ' . $appointment->user->name : null,
                $appointment->case?->case_number ? 'بخصوص القضية: ' . $appointment->case->case_number : null,
            ]);

            $body = implode("\n", $lines);

            // ١) واتساب — من البابِ الواحد بشروطه وحدوده كلِّها
            ClientNotifications::record($type, $client, $appointment->case, [
                'title' => $heading,
                'body' => $body,
                'target' => 'appointments',
                'target_id' => $appointment->id,
                'key' => $appointment->id . ':' . $appointment->starts_at->format('YmdHi'),
            ]);

            // ٢) بريدٌ بالنصّ نفسِه — ولمن لا بريدَ له يمرّ بلا خطأ
            OfficeMailer::send($client->email, new ClientEventMail(
                MailKind::AppointmentNotice,
                $heading,
                $body,
                (string) $client->name,
                $appointment->case?->case_number,
            ));
        } catch (\Throwable $e) {
            Log::error('Appointment notice failed for ' . $appointment->id . ': ' . $e->getMessage());
        }
    }
}
