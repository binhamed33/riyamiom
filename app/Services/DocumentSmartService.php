<?php

namespace App\Services;

/**
 * يستنتج معلومات المستند تلقائياً من اسم الملف
 * (نوع المستند + التاريخ) ليقترحها عند الرفع دون إدخال يدوي.
 */
class DocumentSmartService
{
    /**
     * قائمة الأنواع مرتبة: الأطول/الأدق أولاً.
     *
     * @return array<int, array{label: string, keywords: string[]}>
     */
    public static function typeCatalog(): array
    {
        return [
            ['label' => 'مذكرة دفاع', 'keywords' => ['مذكرة دفاع', 'مذكره دفاع', 'مذكرة', 'مذكره', 'دفاع']],
            ['label' => 'عقد إيجار', 'keywords' => ['عقد ايجار', 'عقد إيجار']],
            ['label' => 'عقد', 'keywords' => ['عقد']],
            ['label' => 'حكم', 'keywords' => ['حكم']],
            ['label' => 'توكيل', 'keywords' => ['توكيل']],
            ['label' => 'لائحة اعتراضية', 'keywords' => ['لائحة اعتراضية', 'لائحه اعتراضيه']],
            ['label' => 'لائحة', 'keywords' => ['لائحة', 'لائحه']],
            ['label' => 'طلب', 'keywords' => ['طلب']],
            ['label' => 'محضر', 'keywords' => ['محضر']],
            ['label' => 'إشعار', 'keywords' => ['اشعار', 'إشعار']],
            ['label' => 'بطاقة شخصية', 'keywords' => ['بطاقة', 'البطاقة', 'كارت']],
            ['label' => 'سند', 'keywords' => ['سند']],
            ['label' => 'فاتورة', 'keywords' => ['فاتورة', 'فاتوره']],
            ['label' => 'تقرير', 'keywords' => ['تقرير']],
            ['label' => 'إقرار', 'keywords' => ['اقرار', 'إقرار']],
            ['label' => 'قرار', 'keywords' => ['قرار']],
            ['label' => 'صورة مستند', 'keywords' => ['صورة']],
        ];
    }

    /**
     * استنتاج نوع المستند وتاريخه من اسم الملف.
     *
     * @return array{type: string|null, date: string|null}
     */
    public static function inferFromFilename(string $filename): array
    {
        $raw = mb_strtolower(preg_replace('/\.[^.]+$/', '', $filename));

        $date = null;
        if (preg_match('/(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})/', $raw, $m)) {
            $day = (int) $m[1];
            $month = (int) $m[2];
            $year = (int) $m[3];
            if ($day > 31) {
                $day = (int) $m[2];
                $month = (int) $m[1];
            }
            if (checkdate($month, $day, $year)) {
                $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        } elseif (preg_match('/(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})/', $raw, $m)) {
            $day = (int) $m[3];
            $month = (int) $m[2];
            $year = (int) $m[1];
            if (checkdate($month, $day, $year)) {
                $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        // تطبيع الفواصل لاستخراج النوع (بعد استخراج التاريخ أولاً)
        $clean = preg_replace('/[-_]+/', ' ', $raw);

        $type = null;
        foreach (self::typeCatalog() as $entry) {
            foreach ($entry['keywords'] as $keyword) {
                if (mb_strpos($clean, mb_strtolower($keyword)) !== false) {
                    $type = $entry['label'];
                    break 2;
                }
            }
        }

        return ['type' => $type, 'date' => $date];
    }
}