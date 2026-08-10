<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * يحوّل الملاحظات المكتوبة بلغة طبيعية إلى إجراءات مقترحة
 * (ملاحظة / اتصال / مهمة / موعد) مع حقل تاريخ عند الحاجة.
 * التحليل قواعدي (Rule-based) بدون نداءات خارجية — سريع ومتوقع.
 */
class NaturalActionParser
{
    public const TYPES = ['note', 'call', 'task', 'appointment'];

    /**
     * @return array<int, array{type: string, title: string, content: string|null, due_date: string|null}>
     */
    public function parse(string $message): array
    {
        $text = trim($message);
        if ($text === '') {
            return [];
        }

        $dateInfo = $this->extractDate($text);
        $dueDate = $dateInfo['due_date'];
        $clean = $dateInfo['clean'];

        $actions = [];

        // 1) اتصال: "اتصلت بـ ..." / "كلمت ..." / "تواصلت مع ..."
        if ($this->containsAny($clean, ['اتصلت', 'اتصلنا', 'كلمت', 'كلمنا', 'تواصلت', 'تواصلنا', 'رنيت', 'اتصل'])) {
            $subject = $this->subjectAfter($clean, ['اتصلت', 'اتصلنا', 'كلمت', 'كلمنا', 'تواصلت', 'تواصلنا', 'رنيت', 'اتصل']);
            $actions[] = [
                'type' => 'call',
                'title' => 'اتصال مكتوب — ' . ($subject ?: 'الطرف'),
                'content' => $subject ? ('تم الاتصال بـ ' . $subject . '.') : $clean,
                'due_date' => null,
            ];
        }

        // 2) موعد: "موعد ... غداً" / "حجز ..." / "سأحضر ..."
        if ($this->containsAny($clean, ['موعد', 'حجزت', 'حجز', 'سأحضر', 'سيحضر', 'اجتماع', 'مقابلة', 'أحجز'])) {
            $subject = $this->subjectAfter($clean, ['موعد', 'حجزت', 'حجز', 'سأحضر', 'سيحضر', 'اجتماع', 'مقابلة', 'أحجز']);
            $title = $subject ? ('موعد — ' . $subject) : ($dueDate ? ('موعد — ' . $dueDate) : 'موعد');
            $actions[] = [
                'type' => 'appointment',
                'title' => $title,
                'content' => $subject ? $clean : null,
                'due_date' => $dueDate,
            ];
        }

        // 3) مهمة/متابعة: "سيرسل ..." / "يجب ..." / "بانتظار ..." / "يحتاج ..."
        if ($this->containsAny($clean, ['سيرسل', 'سأرسل', 'سيتم إرسال', 'يجب', 'لازم', 'احتاج', 'بحاجة', 'بانتظار', 'بنتظار', 'ينتظر', 'يراجع', 'يجهز', 'يتأكد', 'أرسل', 'سأحجز', 'سيحجز', 'أعتقد'])) {
            $subject = $this->subjectAfter($clean, ['سيرسل', 'سأرسل', 'سيتم إرسال', 'يجب', 'لازم', 'احتاج', 'بحاجة', 'بانتظار', 'بنتظار', 'ينتظر', 'يراجع', 'يجهز', 'يتأكد', 'أرسل', 'سأحجز', 'سيحجز', 'أعتقد']);
            $actions[] = [
                'type' => 'task',
                'title' => 'متابعة — ' . ($subject ?: $clean),
                'content' => $clean,
                'due_date' => $dueDate,
            ];
        }

        // 4) ملاحظة عامة (أخيراً — اذا لم يُستخرج شيء أو استخرج غير مهمة)
        $hasActional = $this->containsAny($clean, ['اتصل', 'كلم', 'تواصل', 'موعد', 'حجز', 'سأحضر', 'سيرسل', 'سأرسل', 'يجب', 'لازم', 'احتاج', 'بحاجة', 'بانتظار', 'يراجع', 'يجهز']);
        if (empty($actions) || !$hasActional) {
            $actions[] = [
                'type' => 'note',
                'title' => mb_substr($clean, 0, 90),
                'content' => $clean,
                'due_date' => $dueDate && empty($actions) ? $dueDate : null,
            ];
        }

        // حد أقصى 3 إجراءات مقترحة
        return array_slice(array_values($actions), 0, 3);
    }

    /**
     * استخراج التاريخ (اليوم/غداً/بعد X يوم/تاريخ صريح) وإرجاع النص بعد تنظيفه.
     *
     * @return array{clean: string, due_date: string|null}
     */
    protected function extractDate(string $text): array
    {
        $clean = $text;
        $due = null;

        if (mb_strpos($clean, 'بعد غد') !== false) {
            $due = Carbon::now()->addDays(2)->toDateString();
            $clean = str_replace(['بعد غد', 'بعد غدا'], '', $clean);
        } elseif (mb_strpos($clean, 'غدا') !== false || mb_strpos($clean, 'غداً') !== false) {
            $due = Carbon::now()->addDay()->toDateString();
            $clean = str_replace(['غداً', 'غدا'], '', $clean);
        } elseif (mb_strpos($clean, 'اليوم') !== false) {
            $due = Carbon::now()->toDateString();
            $clean = str_replace('اليوم', '', $clean);
        } elseif (preg_match('/بعد\s+(\d+)\s*(يوم|أيام|ساعة|ساعات)/u', $clean, $m)) {
            $amount = (int) $m[1];
            $unit = $m[2];
            $date = str_contains($unit, 'يوم') ? Carbon::now()->addDays($amount) : Carbon::now()->addHours($amount);
            $due = $date->toDateString();
            $clean = preg_replace('/بعد\s+\d+\s*(يوم|أيام|ساعة|ساعات)/u', '', $clean);
        } elseif (preg_match('/\b(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})\b/u', $clean, $m) && checkdate((int) $m[2], (int) $m[1], (int) ($m[3] < 100 ? 2000 + $m[3] : $m[3]))) {
            $year = (int) ($m[3] < 100 ? 2000 + $m[3] : $m[3]);
            $due = sprintf('%04d-%02d-%02d', $year, (int) $m[2], (int) $m[1]);
            $clean = preg_replace('/\b\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{2,4}\b/u', '', $clean);
        }

        return ['clean' => trim(preg_replace('/\s+/u', ' ', $clean)), 'due_date' => $due];
    }

    protected function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (mb_strpos($text, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    protected function subjectAfter(string $text, array $verbs): string
    {
        foreach ($verbs as $verb) {
            $pos = mb_strpos($text, $verb);
            if ($pos !== false) {
                $rest = trim(mb_substr($text, $pos + mb_strlen($verb)));
                $rest = trim(preg_replace('/^(بـ|مع|على|الي|الى|في|عن)\s+/u', '', $rest));
                $rest = trim($rest);
                $rest = preg_replace('/(^[.,:;،؛.!؟\s]+|[.,:;،؛.!؟\s]+$)/u', '', $rest);
                if ($rest !== '') {
                    $parts = mb_substr($rest, 0, 60);
                    // قص عند أول حرف إيقاف شائع
                    if (preg_match('/^(.*?)(\s+(وقال|وأن|وسيقوم|سأرسل|ويحتاج|ويجب)،?)/u', $parts, $sm)) {
                        return trim($sm[1]);
                    }
                    return $parts;
                }
            }
        }
        return '';
    }
}