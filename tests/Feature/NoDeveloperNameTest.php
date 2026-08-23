<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * اسم المطوّر لا يظهر لمستخدم النظام.
 *
 * كان في ثلاثة مواضع: تذييل الصفحات العامة (يراه موكّلو كل مكتب)،
 * وتذييل نظام المكتب، وخاتمة دليل الاستخدام. والمكتب يُباع لمحامين
 * باسم مُداوَلة لا باسم من كتبها.
 *
 * الفحص على الملفات لا على صفحة واحدة: صفحةٌ واحدة تمرّ ويبقى الاسم
 * في التي لم تُفتح.
 */
class NoDeveloperNameTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, string> */
    private function forbidden(): array
    {
        return ['عبدالرحمن الريامي', 'Abdulrahman Al-Riyami', 'developer_credit'];
    }

    public function test_no_user_facing_file_carries_the_developer_name(): void
    {
        $roots = [resource_path('views'), lang_path(), config_path(), app_path()];
        $hits = [];

        foreach ($roots as $root) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

            foreach ($files as $file) {
                if (!$file->isFile() || !in_array($file->getExtension(), ['php', 'js', 'css'], true)) {
                    continue;
                }

                $body = file_get_contents($file->getPathname());

                foreach ($this->forbidden() as $needle) {
                    if (str_contains($body, $needle)) {
                        $hits[] = str_replace(base_path() . '/', '', $file->getPathname()) . " → {$needle}";
                    }
                }
            }
        }

        $this->assertSame([], $hits, "اسم المطوّر ما زال في:\n" . implode("\n", $hits));
    }

    public function test_the_guide_page_names_the_product_not_a_person(): void
    {
        $guide = file_get_contents(resource_path('views/guide/index.blade.php'));

        $this->assertStringContainsString('مُداوَلة', $guide);
        $this->assertStringNotContainsString('تم تطويره بواسطة', $guide);
    }
}
