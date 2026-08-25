<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ZzScratchNowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'queue.default' => 'database',
            'mail.default' => 'smtp',
            'mail.mailers.smtp' => ['transport' => 'array'],
            'mail.from.address' => 'mudawalah@gmail.com',
            'mail.from.name' => 'مُداوَلة',
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.username' => 'mudawalah@gmail.com',
            'mail.mailers.smtp.password' => 'secret-app-password',
        ]);

        Setting::set('office_name', 'مكتب الاختبار', 'general');
        Setting::set('office_email', 'office@example.om', 'general');
    }

    public function test_now_actually_sends_immediately(): void
    {
        $code = \Illuminate\Support\Facades\Artisan::call('mail:doctor', ['--to' => 'ops@example.om', '--now' => true]);
        $out = \Illuminate\Support\Facades\Artisan::output();

        $jobs = DB::table('jobs')->count();
        $queueName = DB::table('jobs')->value('queue');
        $delivered = Mail::mailer('smtp')->getSymfonyTransport()->messages()->count();

        fwrite(STDERR, "\nexit={$code} jobs={$jobs} queue={$queueName} delivered={$delivered}\n");
        fwrite(STDERR, "claims-sent-directly=" . (str_contains($out, 'أُرسلت مباشرةً') ? 'YES' : 'no') . "\n");
        fwrite(STDERR, substr($out, -400) . "\n");

        $this->assertTrue(true);
    }

    /** Now with a transport that throws, to see if the catch/scrub ever fires. */
    public function test_now_with_throwing_transport(): void
    {
        \Illuminate\Support\Facades\Mail::extend('boom', fn () => new class extends \Symfony\Component\Mailer\Transport\AbstractTransport {
            protected function doSend(\Symfony\Component\Mailer\SentMessage $message): void
            {
                throw new \RuntimeException('535-5.7.8 Username and Password not accepted for mudawalah@gmail.com pw=secret-app-password');
            }
            public function __toString(): string { return 'boom://'; }
        });
        config(['mail.mailers.smtp' => ['transport' => 'boom'], 'mail.mailers.smtp.host' => 'smtp.gmail.com', 'mail.mailers.smtp.username' => 'mudawalah@gmail.com', 'mail.mailers.smtp.password' => 'secret-app-password']);

        $code = \Illuminate\Support\Facades\Artisan::call('mail:doctor', ['--to' => 'ops@example.om', '--now' => true]);
        $out = \Illuminate\Support\Facades\Artisan::output();

        fwrite(STDERR, "\nTHROWING: exit={$code} jobs=" . DB::table('jobs')->count() . "\n");
        fwrite(STDERR, "claims-sent-directly=" . (str_contains($out, 'أُرسلت مباشرةً') ? 'YES' : 'no') . "\n");
        fwrite(STDERR, "shows-535-hint=" . (str_contains($out, 'App Password') ? 'YES' : 'no') . "\n");

        $this->assertTrue(true);
    }
}
