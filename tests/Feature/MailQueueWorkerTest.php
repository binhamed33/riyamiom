<?php

namespace Tests\Feature;

use App\Mail\ClientCaseMail;
use App\Mail\MailKind;
use App\Models\Client;
use App\Models\LegalCase;
use App\Models\Setting;
use App\Models\User;
use App\Services\OfficeMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * الطابور والعامل — بلا محاكاة.
 *
 * Mail::fake() يثبت أنّ الشيفرة نادت الطابور، ولا يثبت أنّ رسالةً
 * تخرج منه. وهذا بالضبط ما أخفق في الاقتراحات من قبل: جرى كلُّ شيء
 * كما يجب، ولم يكن على الخادم عاملٌ يحمل ما أُلقي.
 *
 * فهنا يُستعمل طابور قاعدة البيانات حقيقةً: تُدفع الرسالة، يُقرأ الصفّ
 * من جدول jobs، ثم يُشغَّل العامل نفسه ويُتحقَّق أنّ الرسالة خرجت.
 */
class MailQueueWorkerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // الناقل مصفوفةٌ تحت اسم «smtp».
        //
        // اسمُ الناقل يُحفظ في حمولة المهمّة عند الدفع، فيستعمله العامل
        // عند التنفيذ. ولو بقي الاسم «array» لرفضته isConfigured() عن
        // حق؛ ولو صار smtp حقيقياً لفتح العامل اتصالاً بـ Gmail وعلّق
        // الاختبار حتى المهلة. فالاسمُ smtp والناقلُ مصفوفة: المسار
        // كاملٌ كما في الإنتاج، ولا يغادر بايتٌ هذه الآلة.
        config([
            'queue.default' => 'database',
            'mail.default' => 'smtp',
            'mail.mailers.smtp' => ['transport' => 'array'],
            'mail.from.address' => 'mudawalah@gmail.com',
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.username' => 'mudawalah@gmail.com',
        ]);

        Setting::set('office_name', 'مكتب الاختبار', 'general');
    }

    private function makeCase(): LegalCase
    {
        $user = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $client = Client::factory()->create(['name' => 'موكّل', 'email' => 'client@example.om']);

        return LegalCase::factory()->create([
            'client_id' => $client->id,
            'case_number' => 'Q-1',
            'created_by' => $user->id,
        ]);
    }

    private function dispatchOne(): void
    {
        $case = $this->makeCase();
        $result = OfficeMailer::send('client@example.om', new ClientCaseMail(MailKind::CaseCreated, $case));

        $this->assertSame(OfficeMailer::SENT, $result['status']);
    }

    /** ما سلّمه العامل إلى الناقل فعلاً. */
    private function delivered(): \Illuminate\Support\Collection
    {
        return Mail::mailer('smtp')->getSymfonyTransport()->messages();
    }

    /** الرسالة تنزل الطابور المسمّى «mail» لا الطابور العام. */
    public function test_the_job_lands_on_the_mail_queue(): void
    {
        $this->dispatchOne();

        $this->assertSame(1, DB::table('jobs')->where('queue', 'mail')->count());
        $this->assertSame(0, DB::table('jobs')->where('queue', 'default')->count());
    }

    /**
     * ثم يحملها العامل فعلاً.
     *
     * هذا هو الاختبار الذي كان غيابُه يجعل «أُرسلت» كذبة.
     */
    public function test_the_worker_actually_delivers_what_was_queued(): void
    {
        $this->dispatchOne();

        // ناقلٌ حقيقي لا Mail::fake: يُثبت أنّ رسالةً بُنيت وسُلّمت
        // للناقل بترويستها كاملة، لا أنّ الشيفرة نادت دالّة.
        $this->artisan('queue:work', [
            '--queue' => 'mail',
            '--once' => true,
            '--tries' => 3,
        ])->assertExitCode(0);

        $messages = $this->delivered();

        $this->assertCount(1, $messages, 'لم تخرج رسالة من العامل');
        $this->assertSame(0, DB::table('jobs')->count(), 'الصفّ لم يُستهلك');

        $sent = $messages[0]->getOriginalMessage();

        $this->assertSame('client@example.om', $sent->getTo()[0]->getAddress());
        $this->assertSame('mudawalah@gmail.com', $sent->getFrom()[0]->getAddress());
        $this->assertSame('مكتب الاختبار', $sent->getFrom()[0]->getName());
    }

    /**
     * تسميةُ الطابور تعزل فعلاً.
     *
     * يُصرَّف الطابوران معاً في الإنتاج، لكنّ العزل يبقى صحيحاً: من
     * صرّف «mail» وحده لم يمسّ ما في «default». وعليه بُني ترتيبُ
     * الأولوية في المجدول.
     */
    public function test_naming_a_queue_really_isolates_it(): void
    {
        $this->dispatchOne();

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'Other', 'job' => 'x', 'data' => []]),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        $this->artisan('queue:work', ['--queue' => 'mail', '--once' => true]);

        $this->assertSame(0, DB::table('jobs')->where('queue', 'mail')->count());
        $this->assertSame(1, DB::table('jobs')->where('queue', 'default')->count(), 'عاملُ البريد ابتلع مهمّة غيره');
    }

    /**
     * القضية تُحذف قبل أن يصل العامل.
     *
     * SerializesModels يحفظ المعرّف لا الكائن، فيُعيد جلبه عند التنفيذ.
     * وقضيةٌ اختفت ترمي ModelNotFound — والصحيح أن تُسقَط الرسالة بهدوء
     * لا أن تتراكم في failed_jobs إلى الأبد.
     */
    public function test_a_case_deleted_before_delivery_does_not_pile_up_failures(): void
    {
        $this->dispatchOne();

        LegalCase::query()->forceDelete();

        $this->artisan('queue:work', ['--queue' => 'mail', '--once' => true, '--tries' => 3]);

        $this->assertCount(0, $this->delivered());
        $this->assertSame(0, DB::table('jobs')->count(), 'الصفّ ما زال معلّقاً');
        $this->assertSame(0, DB::table('failed_jobs')->count(), 'رسالةٌ لقضيةٍ محذوفة سُجّلت كإخفاق');
    }

    /** المجدول يحمل عاملَ البريد — بدونه لا يخرج شيء من الطابور. */
    public function test_the_scheduler_carries_the_mail_worker(): void
    {
        $this->assertStringContainsString('--queue=mail', $this->scheduledCommands(), 'لا عامل بريد في المجدول');
        $this->assertStringContainsString('--stop-when-empty', $this->scheduledCommands());
        $this->assertMatchesRegularExpression(
            '/--queue=[^ ]*mail[^\n]*\* \* \* \* \*/',
            $this->scheduledCommands(),
            'عامل البريد لا يعمل كل دقيقة',
        );
    }

    /**
     * كلُّ طابورٍ يُدفَع إليه شيء له من يصرّفه.
     *
     * حدث فعلاً: صُرِّف طابور «mail» وحده وبقي «default» بلا قارئ. ولم
     * يظهر لأنّ QUEUE_CONNECTION كان sync فلا شيء يمرّ بطابور؛ فلمّا صار
     * database توقّف تسليم الاقتراحات بلا رسالة خطأ واحدة — دُفعت إلى
     * مكانٍ لا أحد ينظر فيه.
     */
    public function test_every_queue_that_receives_work_has_someone_draining_it(): void
    {
        $drained = [];

        foreach (explode("\n", $this->scheduledCommands()) as $line) {
            if (preg_match('/--queue=([^ ]+)/', $line, $m)) {
                $drained = array_merge($drained, explode(',', $m[1]));
            }
        }

        // «mail» للبريد، و«default» لكل ما يُدفَع بلا تسمية — ومنه
        // DeliverSuggestionJob.
        foreach (['mail', 'default'] as $queue) {
            $this->assertContains($queue, $drained, "طابور «{$queue}» بلا عامل يصرّفه");
        }
    }

    /** والاقتراح المدفوع فعلاً يُلتقَط. */
    public function test_a_suggestion_job_is_picked_up_by_the_same_worker(): void
    {
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode([
                'uuid' => 'test',
                'displayName' => 'App\\Jobs\\DeliverSuggestionJob',
                'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                'maxTries' => 1,
                'data' => ['commandName' => 'App\\Jobs\\DeliverSuggestionJob', 'command' => serialize(new \App\Jobs\DeliverSuggestionJob(999))],
            ]),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        $this->artisan('queue:work', ['--queue' => 'mail,default', '--once' => true, '--tries' => 1]);

        $this->assertSame(0, DB::table('jobs')->count(), 'الاقتراح بقي في الطابور — لا أحد يصرّفه');
    }

    private function scheduledCommands(): string
    {
        return collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->map(fn ($e) => $e->command . ' ' . $e->expression)
            ->implode("\n");
    }
}
