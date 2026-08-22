<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * ألوان الرسوم البيانية.
 *
 * العطل الذي تحرسه: كانت الصفحات تمرّر النصّ 'var(--accent)' إلى
 * Chart.js. والـ<canvas> ليس فيه تتالي CSS — لا يعرف متغيّرات CSS.
 * المتصفّح يرفض القيمة ويرجع إلى افتراضي سياق الرسم: الأسود. فكانت
 * كل الأعمدة والخطوط تُرسم سوداء في الوضعين معاً، ولا رسالة خطأ.
 *
 * لا يكفي إصلاح الصفحتين: أي صفحة رسوم جديدة قد تكرّر الخطأ نفسه،
 * فيجرد الاختبار القوالب كلها.
 */
class ChartColorTest extends TestCase
{
    /** @return array<int, string> */
    private function chartViews(): array
    {
        $files = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));

        foreach ($it as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $html = file_get_contents($file->getPathname());

            if (str_contains($html, 'new Chart(')) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    public function test_no_canvas_colour_is_a_css_variable_string(): void
    {
        $offenders = [];

        foreach ($this->chartViews() as $file) {
            foreach (explode("\n", file_get_contents($file)) as $n => $line) {
                if (str_starts_with(trim($line), '//') || str_starts_with(trim($line), '*')) {
                    continue;   // تعليق يشرح العطل ليس العطل
                }

                // لون يُسند إلى الرسم وقيمته متغيّر CSS
                if (preg_match('/(backgroundColor|borderColor|pointBackgroundColor|pointBorderColor|hoverBorderColor|titleColor|bodyColor|fillStyle|strokeStyle)\s*:\s*[^,\n]*var\(--/', $line)) {
                    $offenders[] = str_replace(resource_path('views') . '/', '', $file) . ':' . ($n + 1);
                }

                // أو مصفوفة ألوان فيها متغيّر CSS
                if (preg_match('/Colors?\s*=\s*\[[^\]]*var\(--/', $line)) {
                    $offenders[] = str_replace(resource_path('views') . '/', '', $file) . ':' . ($n + 1);
                }
            }
        }

        $this->assertSame([], $offenders,
            "لون رسم بيانيّ قيمته متغيّر CSS — الـcanvas سيرسمه أسود:\n" . implode("\n", $offenders));
    }

    public function test_every_chart_view_uses_the_shared_resolver(): void
    {
        $missing = [];

        foreach ($this->chartViews() as $file) {
            $html = file_get_contents($file);

            if (!str_contains($html, 'MdChart')) {
                $missing[] = str_replace(resource_path('views') . '/', '', $file);
            }
        }

        $this->assertSame([], $missing,
            "قالب فيه رسم بيانيّ لا يستعمل MdChart — ألوانه لن تتبع سمة المكتب:\n" . implode("\n", $missing));
    }

    public function test_the_resolver_ships_in_the_layout_before_page_scripts(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString("@include('partials.chart-theme')", $layout);

        // لا بدّ أن يسبق @stack('scripts') وإلا لم يكن MdChart معرّفاً بعد
        $this->assertLessThan(
            strpos($layout, "@stack('scripts')"),
            strpos($layout, "partials.chart-theme"),
            'MdChart يُحمَّل بعد سكربتات الصفحات — سيكون غير معرّف حين تبنيها.',
        );
    }

    public function test_the_shared_script_carries_a_nonce(): void
    {
        // سياسة CSP في المكتب تحجب أي سكربت بلا nonce بصمت
        $partial = file_get_contents(resource_path('views/partials/chart-theme.blade.php'));

        $this->assertStringContainsString('nonce="{{ $cspNonce ?? \'\' }}"', $partial);
    }

    public function test_the_palette_is_defined_for_both_modes(): void
    {
        $partial = file_get_contents(resource_path('views/partials/chart-theme.blade.php'));

        $this->assertMatchesRegularExpression('/light:\s*\[/', $partial);
        $this->assertMatchesRegularExpression('/dark:\s*\[/', $partial);

        // ألوان الحالة محجوزة ومستقلّة عن ألوان الفئات
        $this->assertStringContainsString('STATUS', $partial);
        $this->assertStringContainsString('good', $partial);
        $this->assertStringContainsString('bad', $partial);
    }
}
