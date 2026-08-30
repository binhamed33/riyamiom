<?php

namespace Tests\Feature;

use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * كل مفتاح ترجمةٍ يُستدعى له تعريفٌ في اللغتين.
 *
 * ═══ العطل الذي وُضع له ═══
 *
 * `__('app.add')` كان يُستدعى في زرّين داخل صفحة القضية بلا تعريف، فيقرأ
 * المستخدم على الزرّ نصّ المفتاح حرفياً: «app.add». ولارافيل تُرجع المفتاح
 * كما هو حين لا تجده — لا تُخطئ ولا تُسجّل شيئاً، فيمرّ العطل إلى الإنتاج
 * ولا يكشفه إلا عينُ من يفتح تلك الصفحة بعينها.
 *
 * فالفحص يمسح الاستدعاءات كلّها بدل أن يعدّ مفاتيح بأعيانها: مفتاحٌ جديد
 * بلا ترجمة يسقط هنا يوم يُكتب، لا يوم يراه عميل.
 */
class TranslationKeysTest extends TestCase
{
    /** @return array<string, list<string>> المفتاح ← الملفات التي تستدعيه */
    private function usedKeys(): array
    {
        $root = dirname(__DIR__, 2);
        $used = [];

        $files = array_merge(
            $this->phpFiles($root . '/resources/views'),
            $this->phpFiles($root . '/app'),
        );

        foreach ($files as $file) {
            $code = (string) file_get_contents($file);

            // الاقتباسان معاً: كان النمط يقرأ المفرد وحده، فمرّ
            // `__("app.other")` سنةً كاملة يُقرأ نصّاً خاماً على الشاشة.
            $patterns = [
                '/__\(\s*\'([a-z_]+)\.([a-z0-9_.]+)\'/',
                '/__\(\s*"([a-z_]+)\.([a-z0-9_.]+)"/',
                '/@lang\(\s*\'([a-z_]+)\.([a-z0-9_.]+)\'/',
                '/@lang\(\s*"([a-z_]+)\.([a-z0-9_.]+)"/',
                '/trans\(\s*\'([a-z_]+)\.([a-z0-9_.]+)\'/',
                '/trans\(\s*"([a-z_]+)\.([a-z0-9_.]+)"/',
            ];

            foreach ($patterns as $pattern) {
                preg_match_all($pattern, $code, $matches, PREG_SET_ORDER);

                foreach ($matches as $match) {
                    // مفتاحٌ يُبنى تمامه وقت التشغيل (`app.priority_` . $value)
                    // لا يُفحص هنا: جزؤه الثابت وحده ليس مفتاحاً.
                    if (str_ends_with($match[2], '_') || str_ends_with($match[2], '.')) {
                        continue;
                    }

                    $used["{$match[1]}.{$match[2]}"][] = str_replace($root . '/', '', $file);
                }
            }
        }

        return $used;
    }

    /** @return list<string> */
    private function phpFiles(string $dir): array
    {
        $files = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

        foreach ($it as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /** @return array<string, mixed> */
    private function definedKeys(string $locale): array
    {
        $root = dirname(__DIR__, 2);
        $keys = [];

        foreach (glob($root . "/lang/{$locale}/*.php") as $file) {
            $group = basename($file, '.php');

            foreach (Arr::dot(require $file) as $key => $value) {
                $keys["{$group}.{$key}"] = $value;
            }
        }

        return $keys;
    }

    #[DataProvider('locales')]
    public function test_every_used_key_is_defined(string $locale): void
    {
        $defined = $this->definedKeys($locale);
        $this->assertNotEmpty($defined, "لا ترجمات محمّلة للغة {$locale}");

        $missing = [];
        foreach ($this->usedKeys() as $key => $files) {
            if (!array_key_exists($key, $defined)) {
                $missing[] = $key . '  ← ' . implode(', ', array_unique(array_slice($files, 0, 2)));
            }
        }

        $this->assertSame([], $missing, sprintf(
            "مفاتيح تُستدعى بلا ترجمة في «%s» — سيقرأها المستخدم نصاً خاماً:\n  %s",
            $locale,
            implode("\n  ", $missing),
        ));
    }

    /** واللغتان متكافئتان: مفتاحٌ في إحداهما دون الأخرى يُظهر نصّه للنصف الآخر. */
    public function test_both_locales_define_the_same_keys(): void
    {
        $ar = array_keys($this->definedKeys('ar'));
        $en = array_keys($this->definedKeys('en'));

        $this->assertSame([], array_values(array_diff($ar, $en)), 'مفاتيح عربية بلا مقابلٍ إنجليزي');
        $this->assertSame([], array_values(array_diff($en, $ar)), 'مفاتيح إنجليزية بلا مقابلٍ عربي');
    }

    /** @return list<array{string}> */
    public static function locales(): array
    {
        return [['ar'], ['en']];
    }
}
