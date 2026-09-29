<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Tests\TestCase;

/**
 * سجلٌّ لا يُكتب لا يُسقط الصفحة.
 *
 * ═══ ما وقع ═══
 *
 * امتلأ قرصُ الخادم، فصار كلُّ خطأٍ في مكتب الوالد صفحةً بيضاءَ برمز
 * 500 وجسمٍ فارغ: Monolog يرمي «Writing to the log file failed» من
 * داخل المُبلِّغ، فيخرج الاستثناءُ من معالج الاستثناءات نفسِه ولا يجد
 * PHP من يعالجه. أُعيد إنتاجُه محلّياً بقرصٍ ممتلئ: الحالةُ 500 والجسمُ
 * صفرُ بايت — «This page isn't working».
 */
class LogWriteFallbackTest extends TestCase
{
    use RefreshDatabase;

    private string $phpLog;

    private string|false $previousPhpLog;

    protected function setUp(): void
    {
        parent::setUp();

        // ما يكتبه error_log يُلتقط هنا لا على شاشة الاختبار
        $this->phpLog = tempnam(sys_get_temp_dir(), 'php-error-log-');
        $this->previousPhpLog = ini_get('error_log');
        ini_set('error_log', $this->phpLog);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', (string) $this->previousPhpLog);
        @unlink($this->phpLog);

        parent::tearDown();
    }

    /** مُخرِجٌ يخيب كما يخيب ملفٌّ على قرصٍ ممتلئ — بالرسالة نفسِها التي يبنيها Monolog. */
    private function breakTheLog(string $channel): void
    {
        $handler = new class extends AbstractProcessingHandler {
            protected function write(LogRecord $record): void
            {
                throw new \UnexpectedValueException(
                    'Writing to the log file failed: Write of 512 bytes failed with errno=28 No space left on device'
                    . "\nThe exception occurred while attempting to log: " . $record->message
                );
            }
        };

        // ‏pushHandler يضعه في المقدّمة: كلُّ سطرٍ يمرّ به أوّلاً فيخيب
        Log::channel($channel)->getLogger()->pushHandler($handler);
    }

    /** الكتابةُ تخيب ⇐ لا استثناء، وسطرٌ واحد في سجلّ PHP يقول السبب — بلا نصّ السطر الذي كان سيُكتب. */
    public function test_a_failing_log_write_does_not_throw_and_names_the_reason_in_the_php_log(): void
    {
        $this->breakTheLog('stack');

        Log::channel('stack')->error('سطرٌ فيه اسم الموكّل أحمد');
        Log::channel('stack')->error('سطرٌ ثانٍ فيه اسم موكّلٍ آخر');

        $php = file_get_contents($this->phpLog);

        $this->assertStringContainsString('[mudawala] log write failed', $php);
        $this->assertStringContainsString('No space left on device', $php);
        $this->assertStringNotContainsString('أحمد', $php, 'نصُّ السطر لا يُنقل إلى سجلّ PHP — قد يحمل اسمَ موكّل');
        $this->assertSame(1, substr_count($php, 'log write failed'), 'السببُ الواحد يُقال مرّةً في الطلب لا مع كلّ سطر');
    }

    /** والقناتان اللتان تُكتبان مباشرةً محميّتان كذلك — سطرٌ لكلٍّ منهما. */
    public function test_single_and_daily_channels_are_covered_too(): void
    {
        foreach (['single', 'daily'] as $channel) {
            $this->breakTheLog($channel);
            Log::channel($channel)->error('x');
        }

        $this->assertSame(2, substr_count(file_get_contents($this->phpLog), 'log write failed'));
    }

    /**
     * صفحةٌ تُخطئ وسجلٌّ لا يُكتب ⇐ رسالةٌ عربيّةٌ وتحويلٌ كما لو كان
     * السجلُّ يعمل — لا 500 بجسمٍ فارغ.
     */
    public function test_an_erroring_page_with_a_dead_log_still_gets_the_arabic_error_path(): void
    {
        config(['logging.default' => 'stack']);
        $this->breakTheLog('stack');

        Route::middleware('web')->get('/__log-fallback-boom', function () {
            throw new \RuntimeException('boom');
        });

        $response = $this->get('/__log-fallback-boom');

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('No space left on device', file_get_contents($this->phpLog));
    }

    /** الإعدادُ نفسُه يحمل التنبيت — حذفُه من config يُعيد الصفحةَ البيضاء. */
    public function test_the_fallback_is_configured_on_every_file_channel(): void
    {
        foreach (['stack', 'single', 'daily'] as $channel) {
            $this->assertContains(
                \App\Logging\LogWriteFallback::class,
                config('logging.channels.' . $channel . '.tap', []),
                'القناة ' . $channel . ' بلا LogWriteFallback'
            );
        }
    }
}
