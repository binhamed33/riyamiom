<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * تدقيق وصولية القوالب.
 *
 * ما يحرسه: أن كل زر أو رابط لا يحمل إلا أيقونة يكون له اسم يُنطَق.
 * موظّف يستعمل قارئ شاشة يسمع «زر» ولا يعرف أهو حذف أم إغلاق —
 * وفي نظام يحذف قضايا، الفرق ليس تجميلياً.
 */
class AccessibilityAuditTest extends TestCase
{
    /** @return array<int, string> */
    private function views(): array
    {
        $files = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));

        foreach ($it as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * تعبير Blade قد يحوي «>» داخله ({{ $msg->url }})، فيقطع أي نمط
     * يقرأ الوسم بـ [^>]. نُفرّغ التعبيرات قبل القراءة لا بعدها.
     */
    private function stripBlade(string $html): string
    {
        return preg_replace(['/\{\{.*?\}\}/s', '/\{!!.*?!!\}/s'], '""', $html);
    }

    private function shortPath(string $file): string
    {
        return str_replace(resource_path('views') . '/', '', $file);
    }

    public function test_every_icon_only_control_has_a_name_a_screen_reader_can_read(): void
    {
        $unnamed = [];

        foreach ($this->views() as $file) {
            $html = $this->stripBlade(file_get_contents($file));

            preg_match_all('/<(button|a)\b(.*?)>(.*?)<\/\1>/s', $html, $matches, PREG_SET_ORDER);

            foreach ($matches as $m) {
                [$whole, , $attrs, $inner] = $m;

                if (!str_contains($inner, '<svg')) {
                    continue;   // ليس زرّ أيقونة
                }

                // نصّ ظاهر بعد إزالة الأيقونات والوسوم
                $visible = trim(preg_replace(
                    ['/<svg.*?<\/svg>/s', '/<[^>]+>/'],
                    '',
                    $inner
                ));

                if ($visible !== '') {
                    continue;
                }

                // نصّ يُكتب في وقت التشغيل عبر Alpine اسمٌ أيضاً
                if (str_contains($inner, 'x-text')) {
                    continue;
                }

                if (preg_match('/aria-label|aria-labelledby|title=|sr-only/', $attrs . $inner)) {
                    continue;
                }

                $line = substr_count(substr($html, 0, strpos($html, $whole)), "\n") + 1;
                $unnamed[] = $this->shortPath($file) . ':' . $line;
            }
        }

        $this->assertSame([], $unnamed, "أزرار أيقونة بلا اسم منطوق:\n" . implode("\n", $unnamed));
    }

    public function test_every_image_carries_an_alt(): void
    {
        $missing = [];

        foreach ($this->views() as $file) {
            preg_match_all('/<img\b[^>]*>/', $this->stripBlade(file_get_contents($file)), $m);

            foreach ($m[0] as $tag) {
                if (!preg_match('/\balt=|:alt=|x-bind:alt=/', $tag)) {
                    $missing[] = $this->shortPath($file) . ': ' . substr($tag, 0, 70);
                }
            }
        }

        $this->assertSame([], $missing, "صور بلا بديل نصّي:\n" . implode("\n", $missing));
    }

    public function test_the_page_declares_its_language_and_direction(): void
    {
        $layouts = ['layouts/app.blade.php', 'client-portal/layout.blade.php'];

        foreach ($layouts as $layout) {
            $path = resource_path('views/' . $layout);

            if (!file_exists($path)) {
                continue;
            }

            $html = $this->stripBlade(file_get_contents($path));

            $this->assertMatchesRegularExpression('/<html[^>]*\bdir=/', $html, $layout);
            $this->assertMatchesRegularExpression('/<html[^>]*\blang=/', $html, $layout);
        }
    }
}
