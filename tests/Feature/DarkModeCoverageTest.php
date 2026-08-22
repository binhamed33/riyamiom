<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * تغطية الوضع الداكن.
 *
 * الوضع الداكن في مُداوَلة يعمل بإعادة تعريف أصناف Tailwind الفاتحة في
 * القالب الرئيسي، لا بكتابة بديل داكن في كل صفحة. الثمن: صنف فاتح جديد
 * يُضاف إلى أي صفحة يبقى فاتحاً في الوضع الداكن ولا يشتكي أحد — حتى يراه
 * مستخدم على لوحة بيضاء وسط شاشة سوداء.
 *
 * هذا الاختبار يجرد الأصناف الفاتحة المستعملة فعلاً في القوالب، ويطالب
 * القالب الرئيسي بأن يكون لكلٍّ منها تعريف داكن.
 */
class DarkModeCoverageTest extends TestCase
{
    /**
     * أصناف تعمل في الوضعين فلا تحتاج بديلاً:
     * خلفيات مصمتة داكنة أصلاً (bg-red-600) ونصّها الأبيض فوقها.
     */
    private const NEEDS_NO_DARK_VARIANT = '/-(400|500|600|700|800|900)$/';

    private function layout(): string
    {
        return file_get_contents(resource_path('views/layouts/app.blade.php'));
    }

    /** @return array<string, string> الصنف => أول قالب استُعمل فيه */
    private function lightClassesInViews(): array
    {
        $palette = 'gray|slate|zinc|neutral|stone|red|green|blue|yellow|amber|orange|purple|indigo|emerald|teal|pink|rose|cyan|sky|lime|violet';
        $pattern = '/\b((?:bg|text|border|divide)-(?:' . $palette . ')-(?:50|100|200|300))\b/';

        $found = [];

        foreach ($this->views() as $file) {
            preg_match_all($pattern, file_get_contents($file), $m);

            foreach ($m[1] as $class) {
                // النصّ بدرجة فاتحة (text-gray-300) مقروء على داكن — لا يلزمه بديل
                if (str_starts_with($class, 'text-') && preg_match('/-(100|200|300)$/', $class)) {
                    continue;
                }

                $found[$class] ??= str_replace(resource_path('views') . '/', '', $file);
            }
        }

        ksort($found);

        return $found;
    }

    /** @return array<int, string> */
    private function views(): array
    {
        $files = [];

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));

        foreach ($it as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')
                && !str_contains($file->getPathname(), 'layouts/app.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /** @return array<int, string> الأصناف التي للقالب تعريف داكن لها */
    private function darkOverrides(): array
    {
        preg_match_all(
            '/\[data-theme="dark"\]\s+\.([a-zA-Z0-9\\\\:_\/-]+)/',
            $this->layout(),
            $m
        );

        return array_map(fn ($c) => str_replace('\\', '', $c), $m[1]);
    }

    public function test_every_light_utility_used_in_a_view_has_a_dark_definition(): void
    {
        $covered = $this->darkOverrides();
        $missing = [];

        foreach ($this->lightClassesInViews() as $class => $file) {
            if (preg_match(self::NEEDS_NO_DARK_VARIANT, $class)) {
                continue;
            }

            if (!in_array($class, $covered, true)) {
                $missing[] = "{$class}  (أول ظهور: {$file})";
            }
        }

        $this->assertSame([], $missing, "أصناف فاتحة بلا تعريف في الوضع الداكن:\n" . implode("\n", $missing));
    }

    public function test_the_dark_palette_is_defined_before_the_page_paints(): void
    {
        $layout = $this->layout();

        // السمة تُكتب على <html> في الخادم، فلا ومضة بيضاء قبل عمل السكربت
        $this->assertStringContainsString('data-theme="{{ $appearanceMode }}"', $layout);
        $this->assertStringContainsString('html[data-theme="dark"]', $layout);
        $this->assertStringContainsString('color-scheme: dark', $layout);
    }
}
