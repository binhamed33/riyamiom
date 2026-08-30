<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * كل مفتاح إعداداتٍ يُقرأ له مفتاحٌ يُكتب.
 *
 * ═══ العطل الذي وُضع له ═══
 *
 * رأس ملف القضية المطبوع كان يقرأ `Setting::get('phone')` و
 * `Setting::get('email')` — ومفتاحا الإعدادات الحقيقيان `office_phone`
 * و`office_email`. فلم يُخطئ شيء: `Setting::get` تُرجع القيمة الافتراضية
 * بهدوء حين لا تجد المفتاح، فخرج في كل ملفٍ مطبوع هاتفٌ وبريدٌ ثابتان
 * ليسا للمكتب، مهما غيّر بياناته من صفحة الإعدادات.
 *
 * وهذا الصنف لا يكشفه اختبارُ ميزة: الصفحة تُعرض، والـPDF يُبنى، ولا شيء
 * يسقط. لا يُكشف إلا بمقابلة ما يُقرأ بما يُكتب.
 */
class SettingKeysTest extends TestCase
{
    /**
     * كل مفتاحٍ يكتبه النظام في أي موضع.
     *
     * ليس البذّار وصفحة الإعدادات وحدهما: النظام يكتب إعداداتٍ من أوامر
     * الطرفية ومحرّك الأتمتة وشاشة المطوّر. فحصرُ الكاتبين في موضعين
     * يجعل الفحص يصيح على مفاتيح سليمة.
     */
    private function writableKeys(): array
    {
        $root = dirname(__DIR__, 2);
        $keys = [];

        // ['key' => 'office_email', ...] في البذّار
        $seeder = (string) file_get_contents($root . '/database/seeders/SettingsSeeder.php');
        preg_match_all("/'key'\s*=>\s*'([a-z0-9_]+)'/", $seeder, $m);
        $keys = array_merge($keys, $m[1]);

        // Setting::set('key', …) أينما وقعت
        foreach ([$root . '/app', $root . '/resources/views', $root . '/routes', $root . '/database'] as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

            foreach ($it as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $code = (string) file_get_contents($file->getPathname());
                preg_match_all("/Setting::set\(\s*'([a-z0-9_]+)'/", $code, $sets);
                $keys = array_merge($keys, $sets[1]);

                // كتابةٌ ببادئة: `Setting::set('feature_' . $name, …)`. المفتاح
                // يُبنى وقت التشغيل، لكنه يُقرأ صريحاً (`feature_hr`) — فبادئته
                // وحدها تربط الطرفين.
                preg_match_all("/Setting::set\(\s*'([a-z0-9_]+_)'\s*\./", $code, $prefixes);
                $keys = array_merge($keys, array_map(fn ($p) => '@' . $p, $prefixes[1]));

                // updateOrCreate(['key' => 'x'], …) و ->where('key', 'x')->update(…)
                preg_match_all("/\['key'\s*=>\s*'([a-z0-9_]+)'\]/", $code, $upserts);
                $keys = array_merge($keys, $upserts[1]);
            }
        }

        // ما تكتبه صفحة الإعدادات: مفاتيح قواعد التحقّق وخريطة المجموعات
        $controller = (string) file_get_contents($root . '/app/Http/Controllers/SettingController.php');
        preg_match_all("/'([a-z0-9_]+)'\s*=>\s*'(?:general|office|system|appearance|nullable|required|boolean|string|image)[^']*'/", $controller, $m2);
        $keys = array_merge($keys, $m2[1]);

        return array_values(array_unique($keys));
    }

    /** @return array<string, list<string>> المفتاح ← الملفات التي تقرؤه */
    private function readKeys(): array
    {
        $root = dirname(__DIR__, 2);
        $read = [];

        foreach ([$root . '/resources/views', $root . '/app'] as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

            foreach ($it as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $code = (string) file_get_contents($file->getPathname());
                preg_match_all("/Setting::get\(\s*'([a-z0-9_]+)'/", $code, $m);

                foreach ($m[1] as $key) {
                    $read[$key][] = str_replace($root . '/', '', $file->getPathname());
                }
            }
        }

        return $read;
    }

    public function test_every_setting_read_has_a_key_that_can_be_written(): void
    {
        $writable = $this->writableKeys();
        $this->assertNotEmpty($writable, 'لم تُقرأ مفاتيح الإعدادات من البذّار والمتحكّم');

        // البادئات المكتوبة ديناميكياً، مميّزةً بـ@ عن المفاتيح التامّة
        $prefixes = array_map(
            fn ($p) => substr($p, 1),
            array_filter($writable, fn ($k) => str_starts_with($k, '@')),
        );

        $orphans = [];
        foreach ($this->readKeys() as $key => $files) {
            if (in_array($key, $writable, true)) {
                continue;
            }

            // مفتاحٌ يُبنى تمامه وقت التشغيل: `'feature_' . $name`
            if (str_ends_with($key, '_')) {
                continue;
            }

            // أو يُقرأ تامّاً ويُكتب ببادئته
            foreach ($prefixes as $prefix) {
                if (str_starts_with($key, $prefix)) {
                    continue 2;
                }
            }

            $orphans[] = $key . '  ← ' . implode(', ', array_unique(array_slice($files, 0, 2)));
        }

        $this->assertSame([], $orphans, sprintf(
            "مفاتيح إعداداتٍ تُقرأ ولا يكتبها شيء — ستسقط أبداً على قيمتها الافتراضية:\n  %s",
            implode("\n  ", $orphans),
        ));
    }
}
