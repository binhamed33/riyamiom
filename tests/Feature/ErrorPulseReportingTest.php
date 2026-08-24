<?php

namespace Tests\Feature;

use App\Support\ErrorPulse;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * §56 من جهة المكتب: يقرأ سجلّه ويرسل عدداً ونوعاً ومساراً — ولا يرسل
 * نصّ الخطأ، لأنّ نصّه قد يحمل اسم موكّل أو رقم قضية.
 */
class ErrorPulseReportingTest extends TestCase
{
    private string $log;
    private ?string $backup = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->log = storage_path('logs/laravel.log');

        if (File::exists($this->log)) {
            $this->backup = File::get($this->log);
        }
    }

    protected function tearDown(): void
    {
        if ($this->backup !== null) {
            File::put($this->log, $this->backup);
        } elseif (File::exists($this->log)) {
            File::delete($this->log);
        }

        parent::tearDown();
    }

    private function writeLog(string $contents): void
    {
        File::ensureDirectoryExists(dirname($this->log));
        File::put($this->log, $contents);
    }

    public function test_it_counts_todays_errors_and_names_the_last_one(): void
    {
        $today = now()->format('Y-m-d H:i:s');
        $old = now()->subDays(5)->format('Y-m-d H:i:s');

        $this->writeLog(<<<LOG
        [{$old}] production.ERROR: قديم جداً {"exception":"[object] (RuntimeException(code: 0)"}
        [{$today}] production.ERROR: أول {"exception":"[object] (Illuminate\\\\Database\\\\QueryException(code: 42S02)"}
        [{$today}] production.INFO: ليس خطأً
        [{$today}] production.ERROR: The POST method is not supported for route /cases/9. {"exception":"[object] (Symfony\\\\Component\\\\HttpKernel\\\\Exception\\\\MethodNotAllowedHttpException(code: 0)"}
        LOG);

        $pulse = ErrorPulse::summary();

        $this->assertSame(2, $pulse['count'], 'القديم لا يُعدّ، والمعلومة ليست خطأً');
        $this->assertSame('MethodNotAllowedHttpException', $pulse['last_type']);
        $this->assertSame('/cases/9', $pulse['last_route']);
        $this->assertNotNull($pulse['last_at']);
    }

    public function test_it_never_carries_the_error_text_itself(): void
    {
        $today = now()->format('Y-m-d H:i:s');

        // نصّ خطأ يحمل اسم موكّل — كما تفعل أخطاء القاعدة فعلاً
        $this->writeLog("[{$today}] production.ERROR: Duplicate entry 'أحمد الريامي' for key 'clients_name_unique' {\"exception\":\"[object] (Illuminate\\\\Database\\\\UniqueConstraintViolationException(code: 23000)\"}");

        $pulse = ErrorPulse::summary();
        $flat = json_encode($pulse, JSON_UNESCAPED_UNICODE);

        $this->assertSame(1, $pulse['count']);
        $this->assertSame('UniqueConstraintViolationException', $pulse['last_type']);
        $this->assertStringNotContainsString('أحمد الريامي', $flat, 'اسم موكّل تسرّب في النبضة');
        $this->assertStringNotContainsString('Duplicate entry', $flat);
    }

    public function test_a_missing_log_is_silence_not_failure(): void
    {
        if (File::exists($this->log)) {
            File::delete($this->log);
        }

        $pulse = ErrorPulse::summary();

        $this->assertSame(0, $pulse['count']);
        $this->assertNull($pulse['last_type']);
    }

    public function test_a_clean_log_reports_zero(): void
    {
        $this->writeLog("[" . now()->format('Y-m-d H:i:s') . "] production.INFO: كل شيء سليم\n");

        $this->assertSame(0, ErrorPulse::summary()['count']);
    }

    /**
     * بوتات الإنترنت ترمي POST على الصفحة الرئيسة كل يوم. كانت تُدوَّن
     * ERROR فيظهر كل مكتب في اللوحة وكأن فيه عطلاً دائماً، ويفشل فحص
     * الصحة اليومي زوراً. طلبٌ مرفوض من عميل غريب يُدوَّن INFO فقط.
     */
    public function test_bot_post_on_home_is_info_not_error(): void
    {
        \Illuminate\Support\Facades\Log::spy();

        $this->post('/');

        \Illuminate\Support\Facades\Log::shouldNotHaveReceived('error');
        \Illuminate\Support\Facades\Log::shouldHaveReceived('info')
            ->withArgs(fn ($message) => is_string($message) && str_contains($message, 'Client request refused'))
            ->once();
    }
}
