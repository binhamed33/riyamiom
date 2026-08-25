<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * الفحصُ يُفرّق بين «رُفض» و«لم يصل» و«لم أستطع السؤال».
 *
 * ═══ العطل الذي وُضع له ═══
 *
 * كان الفحصُ الجماعي يقرأ رمز الخروج وحده: صفرٌ مقبول وما دونه مرفوض.
 * فأعطى على الخادم عشرة مكاتب «مرفوضة» ولم يكن في واحدٍ منها اعتمادٌ
 * مرفوض — الخيارُ نفسه لم يكن في نسخها بعد، فردّ لارافل «خيارٌ لا
 * يُعرف» ورمزُ خروجه غير صفر.
 *
 * فالمحروس هنا أنّ لكلّ حالةٍ كلمتها الخاصة، لأنّ علاجاتها مختلفة:
 * كلمةُ مرورٍ تُبدَّل، أو منفذٌ يُفتح، أو مكتبٌ يُحدَّث. وخلطُها يرسل
 * المشرف إلى العلاج الخطأ.
 *
 * وخادمُ SMTP هنا حقيقي — مقبسٌ يستمع على هذه الآلة يردّ ٥٣٥ كما يردّ
 * Gmail. لا محاكاةَ صنفٍ ولا mock: المفحوص هو ما تفعله مكتبةُ الإرسال
 * حين يقول لها خادمٌ «لا».
 */
class MailProbeTest extends TestCase
{
    use RefreshDatabase;

    /** @var resource|null */
    private $server = null;

    protected function tearDown(): void
    {
        if (is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
            $this->server = null;
        }

        parent::tearDown();
    }

    /**
     * خادمُ SMTP يردّ ٥٣٥ على كل محاولة اعتماد.
     *
     * يُطبع المنفذُ الذي حصل عليه في أول سطر، فلا حاجة إلى حجز منفذٍ
     * ثابت ولا إلى سباقٍ عليه بين اختبارين متزامنين.
     */
    private function startRejectingServer(): int
    {
        $script = <<<'PHPSCRIPT'
$s = stream_socket_server("tcp://127.0.0.1:0", $e, $m);
$name = stream_socket_get_name($s, false);
echo substr($name, strrpos($name, ':') + 1) . "\n";
flush();
while (($c = @stream_socket_accept($s, 20)) !== false) {
    fwrite($c, "220 fake ESMTP\r\n");
    while (($line = fgets($c)) !== false) {
        if (stripos($line, 'EHLO') === 0 || stripos($line, 'HELO') === 0) {
            fwrite($c, "250-fake\r\n250 AUTH LOGIN PLAIN\r\n");
        } elseif (stripos($line, 'AUTH') === 0) {
            fwrite($c, "535 5.7.8 Username and Password not accepted\r\n");
        } elseif (stripos($line, 'QUIT') === 0) {
            fwrite($c, "221 bye\r\n");
            break;
        } else {
            fwrite($c, "250 ok\r\n");
        }
    }
    fclose($c);
}
PHPSCRIPT;

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

        $this->server = proc_open(
            [PHP_BINARY, '-r', $script],
            $descriptors,
            $pipes,
        );

        $this->assertIsResource($this->server, 'تعذّر تشغيل خادم الاختبار');

        $port = (int) trim((string) fgets($pipes[1]));
        $this->assertGreaterThan(0, $port, 'خادم الاختبار لم يُعلن منفذه');

        return $port;
    }

    private function pointMailAt(string $host, int $port): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.from.address' => 'mudawalah@gmail.com',
            'mail.mailers.smtp' => [
                'transport' => 'smtp',
                'host' => $host,
                'port' => $port,
                'scheme' => null,
                'encryption' => null,
                'username' => 'mudawalah@gmail.com',
                'password' => 'sixteenlowercase',
                'timeout' => 10,
                'local_domain' => 'localhost',
            ],
        ]);
    }

    private function brief(): string
    {
        Artisan::call('mail:doctor', ['--brief' => true, '--probe' => true]);

        return Artisan::output();
    }

    /** ٥٣٥ من الخادم يعني كلمة المرور — لا الشبكة ولا الكود. */
    public function test_a_server_that_says_535_is_reported_as_rejected_credentials(): void
    {
        $this->pointMailAt('127.0.0.1', $this->startRejectingServer());

        $this->assertStringContainsString('probe=rejected', $this->brief());
    }

    /**
     * ومنفذٌ لا أحد عليه يعني الشبكة — لا كلمة المرور.
     *
     * وهذا الفرق هو كلُّ الفائدة: من يقرأ «مرفوض» يبدّل كلمة المرور،
     * ولو كان السبب منفذاً مغلقاً لبدّلها عشر مرّات بلا أثر.
     */
    public function test_a_port_with_nobody_listening_is_reported_as_unreachable(): void
    {
        // ١ منفذٌ محجوز لا يستمع عليه شيء
        $this->pointMailAt('127.0.0.1', 1);

        $this->assertStringContainsString('probe=unreachable', $this->brief());
    }

    /** وسائقٌ لا يُرسل أصلاً لا يُسأل عن اعتماد. */
    public function test_an_unconfigured_mailer_is_not_reported_as_rejected(): void
    {
        config(['mail.default' => 'log']);

        $out = $this->brief();

        $this->assertStringContainsString('probe=not_configured', $out);
        $this->assertStringNotContainsString('probe=rejected', $out);
    }

    /** والفحص بلا --probe لا يفتح اتصالاً أصلاً. */
    public function test_brief_without_probe_never_touches_the_network(): void
    {
        $this->pointMailAt('127.0.0.1', 1);

        Artisan::call('mail:doctor', ['--brief' => true]);

        $this->assertStringContainsString('probe=skipped', Artisan::output());
    }

    /**
     * والسطرُ يخرج بصفرٍ مهما كانت الحال.
     *
     * لأنّ رمز الخروج هو ما أوقع العطل: من يقرؤه لا يعرف أنّ «غير صفر»
     * قد تعني «الخيار غير موجود» لا «الاعتماد مرفوض». فهنا يقول الرمزُ
     * «طُبع التقرير» وحدها، وتُقرأ الحقائق من حقولها.
     */
    public function test_the_brief_line_always_exits_zero_so_no_caller_misreads_it(): void
    {
        $this->pointMailAt('127.0.0.1', 1);

        $this->assertSame(0, Artisan::call('mail:doctor', ['--brief' => true, '--probe' => true]));
        $this->assertStringContainsString('probe=unreachable', Artisan::output());
    }

    /** ولا يحمل السطرُ كلمةَ المرور ولا جزءاً منها. */
    public function test_the_brief_line_carries_no_secret(): void
    {
        $this->pointMailAt('127.0.0.1', 1);

        $out = $this->brief();

        $this->assertStringNotContainsString('sixteenlowercase', $out);
        $this->assertStringContainsString('MAILSTATE', $out);
    }
}
