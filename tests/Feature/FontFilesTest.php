<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * خطوطُ الـPDF في resources/fonts خطوطٌ فعلاً لا صفحاتُ ويب محفوظة.
 *
 * Tajawal-Bold.ttf وTajawal-Regular.ttf كانا صفحتَي GitHub بصيغة HTML حُفظتا
 * باسم ttf. لا يقرؤهما شيءٌ اليوم، لكنّ أوّلَ ملفٍّ يُطبع بخطّ Tajawal كان
 * سيسقط بخطأ خادمٍ في mPDF («not a TrueType font») لا بخطٍّ بديل.
 */
class FontFilesTest extends TestCase
{
    public function test_every_bundled_font_is_a_real_font_file(): void
    {
        $files = glob(resource_path('fonts') . '/*.{ttf,otf}', GLOB_BRACE);
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $magic = (string) file_get_contents($file, false, null, 0, 4);

            // ‏TrueType ‏(00 01 00 00 أو «true») أو OpenType ‏(«OTTO»)
            $this->assertContains($magic, ["\x00\x01\x00\x00", 'true', 'OTTO'], basename($file) . ' ليس ملفَّ خطّ');
        }
    }
}
