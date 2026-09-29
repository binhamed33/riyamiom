<?php

namespace Tests\Feature;

use App\Logging\CappedFileHandler;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Monolog\Logger;
use Tests\TestCase;

/**
 * سجلٌّ له سقف: 47 غيغابايت من مكتبٍ واحد أسقطت كلَّ المكاتب.
 */
class CappedFileHandlerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/capped-log-' . uniqid();
        File::ensureDirectoryExists($this->dir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function logger(int $maxBytes, int $generations = 2): Logger
    {
        $handler = new CappedFileHandler($this->dir . '/laravel.log', $maxBytes, $generations);

        return new Logger('test', [$handler]);
    }

    /** يبلغ السقفَ فيُزاح إلى ‎.1‎ ويبدأ ملفٌّ جديد بالاسم نفسِه. */
    public function test_the_file_rotates_when_it_reaches_the_cap(): void
    {
        $log = $this->logger(2_000);

        for ($i = 0; $i < 60; $i++) {
            $log->error(str_repeat('x', 100));
        }

        $this->assertFileExists($this->dir . '/laravel.log');
        $this->assertFileExists($this->dir . '/laravel.log.1');
        $this->assertLessThan(2_200, filesize($this->dir . '/laravel.log'), 'الحاليُّ لا يتجاوز السقفَ إلا بسطر');
    }

    /** ثلاثةُ أجيالٍ لا أكثر: الأقدمُ يسقط — فلا يعود الملفُّ يكبر بأسماء جديدة. */
    public function test_only_the_configured_generations_are_kept(): void
    {
        $log = $this->logger(1_000, 2);

        for ($i = 0; $i < 400; $i++) {
            $log->error(str_repeat('y', 120));
        }

        $this->assertFileExists($this->dir . '/laravel.log.1');
        $this->assertFileExists($this->dir . '/laravel.log.2');
        $this->assertFileDoesNotExist($this->dir . '/laravel.log.3');

        $total = 0;
        foreach (glob($this->dir . '/laravel.log*') as $f) {
            $total += filesize($f);
        }

        $this->assertLessThan(3 * 1_200, $total, 'مجموعُ ما على القرص محكومٌ بالسقف × الأجيال');
    }

    /** ما بعد الإزاحة يُكتب في الجديد لا في المُزاح. */
    public function test_lines_after_rotation_land_in_the_new_file(): void
    {
        $log = $this->logger(1_000);

        for ($i = 0; $i < 20; $i++) {
            $log->error(str_repeat('z', 100));
        }
        $log->error('after-rotation-marker');

        $this->assertStringContainsString('after-rotation-marker', file_get_contents($this->dir . '/laravel.log'));
    }

    /** قناةُ single في الإعداد هي هذا المعالجُ بسقفه — بالاسم الذي تعرفه ملفّاتُ .env. */
    public function test_the_single_channel_is_capped(): void
    {
        $this->assertSame(CappedFileHandler::class, config('logging.channels.single.handler'));
        $this->assertSame(100 * 1024 * 1024, config('logging.channels.single.with.maxBytes'));

        $handlers = Log::channel('single')->getLogger()->getHandlers();
        $this->assertInstanceOf(CappedFileHandler::class, $handlers[0]);
        $this->assertSame(storage_path('logs/laravel.log'), $handlers[0]->getUrl(), 'القرّاءُ (office:health، النبضة) يقرؤون الاسمَ نفسَه');
    }
}
