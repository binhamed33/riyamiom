<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * لقطات النسخ (§27): قبل كل تعديل تُحفظ الحالة القائمة كاملة،
 * فيستطيع المدير رؤية ما تغيّر واستعادة نسخة سابقة بأمان.
 *
 * الاستعادة لا تمسّ إلا الحقول المصرّح بتعبئتها في النموذج نفسه —
 * لا معرّفات ولا عدّادات ولا طوابع زمنية.
 */
class Revisions
{
    /** يحفظ لقطة من الحالة الحالية قبل أن يكتب التعديل فوقها. */
    public static function capture(Model $subject, ?int $userId = null): void
    {
        try {
            $last = (int) DB::table('revision_snapshots')
                ->where('subject_type', $subject->getMorphClass())
                ->where('subject_id', $subject->getKey())
                ->max('version');

            DB::table('revision_snapshots')->insert([
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'version' => $last + 1,
                'payload' => json_encode($subject->only($subject->getFillable()), JSON_UNESCAPED_UNICODE),
                'created_by' => $userId,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // اللقطة توثيق لا شرط: تعذّرها لا يوقف حفظ التعديل نفسه
            \Illuminate\Support\Facades\Log::warning('Revisions: تعذّر حفظ اللقطة — ' . $e->getMessage());
        }
    }

    /** @return \Illuminate\Support\Collection لقطات هذا العنصر، الأحدث أولاً */
    public static function for(Model $subject)
    {
        return DB::table('revision_snapshots')
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->orderByDesc('version')
            ->get()
            ->map(function ($row) {
                $row->payload = json_decode((string) $row->payload, true) ?: [];

                return $row;
            });
    }

    /**
     * يستعيد نسخة بعينها — وقبلها يلتقط الحالة الحالية، فالاستعادة
     * نفسها قابلة للتراجع عنها.
     */
    public static function restore(Model $subject, int $version, ?int $userId = null): bool
    {
        $row = DB::table('revision_snapshots')
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->where('version', $version)
            ->first();

        if (!$row) {
            return false;
        }

        $payload = json_decode((string) $row->payload, true);
        if (!is_array($payload)) {
            return false;
        }

        self::capture($subject, $userId);

        // لا يُستعاد إلا المصرَّح بتعبئته — والعدّادات والفاعل يبقيان
        $safe = array_intersect_key($payload, array_flip($subject->getFillable()));
        unset($safe['created_by'], $safe['usage_count'], $safe['runs_count'], $safe['last_run_at']);
        $subject->fill($safe)->save();

        return true;
    }
}
