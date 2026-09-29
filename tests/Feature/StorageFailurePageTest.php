<?php

namespace Tests\Feature;

use App\Support\StorageFailure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * قرصٌ ممتلئ ⇐ صفحةٌ تقول ذلك، لا صفحةٌ بيضاء ولا حلقةُ تحويل.
 *
 * كان خطأُ file_put_contents «errno=28» يُعالَج كأيّ خطأ: تحويلٌ إلى
 * لوحة التحكّم برسالةٍ في الجلسة — وقالبُ اللوحة لا يُجمَّع على القرص
 * نفسِه، فيدور الزائرُ أو يرى رسالةً بلا سبب.
 */
class StorageFailurePageTest extends TestCase
{
    use RefreshDatabase;

    private const COMPILE_FAILURE = 'file_put_contents(/srv/storage/framework/views/abc.php): Write of 10803 bytes failed with errno=28 No space left on device';

    public function test_disk_write_failures_are_recognised_by_their_message_anywhere_in_the_chain(): void
    {
        $this->assertTrue(StorageFailure::of(new \ErrorException(self::COMPILE_FAILURE)));
        $this->assertTrue(StorageFailure::of(new \UnexpectedValueException('The stream or file "/x/laravel.log" could not be opened in append mode: Failed to open stream: Permission denied')));
        $this->assertTrue(StorageFailure::of(new \RuntimeException('wrapped', 0, new \ErrorException(self::COMPILE_FAILURE))));

        $this->assertFalse(StorageFailure::of(new \RuntimeException('boom')));
        // ملفٌّ مفقود ليس قرصاً ممتلئاً — عطبُ كودٍ يجب أن يظهر بوجهه
        $this->assertFalse(StorageFailure::of(new \ErrorException('file_get_contents(/srv/public/lib/x.js): Failed to open stream: No such file or directory')));
        // وصلاحيّةٌ مغلقة خارج storage/ ليست منّا
        $this->assertFalse(StorageFailure::of(new \ErrorException('fopen(/etc/shadow): Failed to open stream: Permission denied')));
        $this->assertTrue(StorageFailure::of(new \ErrorException('file_put_contents(/srv/storage/framework/views/a.php): Failed to open stream: Permission denied')));
        $this->assertFalse(StorageFailure::of(new \Illuminate\Database\QueryException('mysql', 'select 1', [], new \Exception('Column not found'))));
    }

    /** الصفحةُ عربيّةٌ، بلا قالبٍ ولا تحويلٍ ولا مسارٍ من الخادم، ولا تُحفظ في ذاكرةٍ وسيطة. */
    public function test_a_page_that_hits_a_full_disk_gets_the_ready_made_arabic_page(): void
    {
        Route::middleware('web')->get('/__disk-full', function () {
            throw new \ErrorException(self::COMPILE_FAILURE);
        });

        $response = $this->get('/__disk-full');

        $response->assertStatus(500);
        $response->assertSee('خادمُ المكتب لا يستطيع الحفظ الآن', false);
        $response->assertDontSee('/srv/storage', false);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertGreaterThan(500, strlen($response->getContent()), 'الجسمُ ليس فارغاً — كروم يعرض صفحتَه هو تحت 512 بايت');
    }

    /** طلبُ JSON يبقى JSON — الواجهةُ تنتظر حقلَ error لا صفحةً. */
    public function test_json_requests_keep_their_json_error(): void
    {
        Route::middleware('web')->get('/__disk-full-json', function () {
            throw new \ErrorException(self::COMPILE_FAILURE);
        });

        $this->getJson('/__disk-full-json')
            ->assertStatus(500)
            ->assertJson(['ok' => false]);
    }
}
