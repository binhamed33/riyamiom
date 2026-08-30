<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * دوران الأجداد والآباء والأبناء (GFS) للنسخ الاحتياطية.
 *
 * ═══ لماذا ═══
 *
 * الاحتفاظ بآخر سبع نسخ يعني أن أقدم ما تملكه عمرُه أسبوع. وعطبٌ دخل
 * البيانات قبل شهر — حذفٌ خاطئ، تعديلٌ صامت — لا يُكتشف عادةً في أسبوع،
 * وحين يُكتشف تكون كل النسخ قد ورثته.
 *
 * فالنسخ تُرقّى: نسخةُ اليوم يومية، وأولى نسخِ الأسبوع تُرقّى أسبوعية،
 * وأولى نسخِ الشهر شهرية، وأولى نسخِ السنة سنوية. كلٌّ يُحفظ في مجلده
 * ويُحذف بقاعدته وحده — فالنسخة الشهرية لا تُمسّ حين تُستبدل اليومية.
 *
 * والترقية نسخٌ لا نقل: النسخة اليومية تبقى مكانها حتى يحين حذفها.
 */
class BackupRotation
{
    /** كم يُحفظ من كل مستوى — والاسم يقول المدة التي يغطّيها */
    public const KEEP = [
        'daily' => 7,      // أسبوع من الرجوع اليومي
        'weekly' => 4,     // شهر من الرجوع الأسبوعي
        'monthly' => 12,   // سنة من الرجوع الشهري
        'yearly' => 3,     // ثلاث سنوات — التزامٌ ورقي غالباً
    ];

    /**
     * يرقّي نسخة اليوم إلى ما تستحقه من مستويات، ثم يحذف الزائد في كل
     * مستوى على حدة. يعيد وصفاً لما جرى ليُطبع ويُدوَّن.
     *
     * @return array{promoted: array<int, string>, removed: array<int, string>}
     */
    public static function rotate(string $backupDir, string $sourceFile, ?CarbonImmutable $now = null): array
    {
        $now = $now ?? CarbonImmutable::now();
        $promoted = [];
        $removed = [];

        if (!is_file($sourceFile)) {
            return ['promoted' => [], 'removed' => []];
        }

        $levels = [
            // المستوى => [هل حان وقته؟, بصمته التي لا تتكرر في المدة]
            'weekly' => [true, 'weekly-' . $now->isoFormat('GGGG-[W]WW')],
            'monthly' => [true, 'monthly-' . $now->format('Y-m')],
            'yearly' => [true, 'yearly-' . $now->format('Y')],
        ];

        foreach ($levels as $level => [$due, $stamp]) {
            $dir = $backupDir . '/' . $level;

            if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
                continue;
            }

            $target = $dir . '/' . $stamp . '.zip';

            // أول نسخة في المدة تُرقّى، وما بعدها لا يُعيد الكتابة —
            // فالنسخة الأسبوعية تمثّل بداية أسبوعها لا آخر يوم فيه
            if (!is_file($target) && @copy($sourceFile, $target)) {
                @chmod($target, 0600);
                $promoted[] = $level . '/' . basename($target);
            }

            foreach (self::prune($dir, self::KEEP[$level]) as $gone) {
                $removed[] = $level . '/' . $gone;
            }
        }

        return ['promoted' => $promoted, 'removed' => $removed];
    }

    /** يحذف ما زاد عن العدد داخل مستوى واحد — الأقدم أولاً. */
    private static function prune(string $dir, int $keep): array
    {
        $files = glob($dir . '/*.zip') ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        $removed = [];
        foreach (array_slice($files, $keep) as $file) {
            if (@unlink($file)) {
                $removed[] = basename($file);
            }
        }

        return $removed;
    }

    /** ما يملكه المكتب الآن من كل مستوى — للعرض في صفحة النسخ. */
    public static function inventory(string $backupDir): array
    {
        $out = ['daily' => [], 'weekly' => [], 'monthly' => [], 'yearly' => []];

        foreach (glob($backupDir . '/backup-*.zip') ?: [] as $file) {
            $out['daily'][] = self::describe($file);
        }

        foreach (['weekly', 'monthly', 'yearly'] as $level) {
            foreach (glob($backupDir . '/' . $level . '/*.zip') ?: [] as $file) {
                $out[$level][] = self::describe($file);
            }
        }

        foreach ($out as $level => $files) {
            usort($out[$level], fn ($a, $b) => $b['at'] <=> $a['at']);
        }

        return $out;
    }

    private static function describe(string $file): array
    {
        return [
            'name' => basename($file),
            'size_mb' => round(filesize($file) / 1024 / 1024, 2),
            'at' => filemtime($file),
        ];
    }
}
