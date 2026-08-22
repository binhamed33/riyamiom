<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * سياسة CSP في هذا النظام لا تسمح بـ 'unsafe-inline' للسكربتات،
 * فأي معالج مضمَّن (onclick / onsubmit) لا ينفّذه المتصفح إطلاقاً.
 *
 * كان ذلك يعني أن تأكيدات الحذف لا تظهر وأن العملية تُنفَّذ مباشرة،
 * وأن زرّي نافذة انتهاء الجلسة لا يعملان. هذا الاختبار يمنع عودتها.
 */
class InlineHandlerCspTest extends TestCase
{
    private const HANDLERS = ['onclick=', 'onsubmit=', 'onchange=', 'oninput=', 'onkeyup=', 'onload=', 'onerror='];

    public function test_the_csp_really_forbids_inline_scripts(): void
    {
        $middleware = file_get_contents(app_path('Http/Middleware/SecurityHeaders.php'));

        $this->assertStringContainsString('script-src', $middleware);
        $this->assertStringNotContainsString("script-src 'unsafe-inline'", $middleware);
        $this->assertStringNotContainsString("'unsafe-inline' 'nonce", $middleware);
    }

    public function test_no_blade_view_uses_an_inline_event_handler(): void
    {
        $offenders = [];

        foreach ($this->bladeFiles() as $file) {
            $contents = file_get_contents($file);
            foreach (self::HANDLERS as $handler) {
                if (str_contains($contents, ' ' . $handler)) {
                    $offenders[] = str_replace(resource_path('views') . '/', '', $file) . ' → ' . rtrim($handler, '=');
                }
            }
        }

        $this->assertSame([], $offenders,
            "معالجات مضمَّنة لا ينفّذها المتصفح تحت سياسة CSP الحالية:\n" . implode("\n", $offenders));
    }

    public function test_destructive_forms_still_ask_before_acting(): void
    {
        // التأكيد انتقل إلى data-confirm الذي يقرأه معالج مفوَّض
        $confirmed = 0;

        foreach ($this->bladeFiles() as $file) {
            $confirmed += substr_count(file_get_contents($file), 'data-confirm=');
        }

        $this->assertGreaterThanOrEqual(15, $confirmed,
            'عدد التأكيدات أقل من المتوقع — قد تكون عملية حذف فقدت تأكيدها');
    }

    /** @return iterable<string> */
    private function bladeFiles(): iterable
    {
        $dir = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));

        foreach ($dir as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                yield $file->getPathname();
            }
        }
    }
}
