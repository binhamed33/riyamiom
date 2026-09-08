<?php

namespace Tests\Feature;

use App\Jobs\ProcessWhatsAppWebhook;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppWebhookEvent;
use App\Services\WhatsApp\InboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * ‏«Data too long for column 'media_id'» — ٧٣٣٨٥ مهمّة أخفقت نهائياً.
 *
 * ═══ ما وقع في مكتبٍ حيّ ═══
 *
 * ‏media_id كُتب varchar(160) على مقاس معرّفات Meta: رقمٌ قصير يُطلب به
 * الملفُّ من مخزنها. ثمّ صار الجسرُ Evolution ولا مخزنَ عنده، فيرسل في
 * مكان المعرّف **عنوانَ الملفّ نفسَه** على شبكة واتساب — ثلاثمئةٍ إلى
 * تسعمئة حرف.
 *
 * فكلُّ صورةٍ تصل المكتبَ تُرفض عند الإدراج، والمهمّةُ تُعاد خمساً،
 * والمكنسةُ تعيد دفعَ الحدث كلَّ خمس دقائق ما دام غيرَ معالَج. فبلغت
 * مهامُّ الإخفاق في مكتبٍ واحد ثلاثةً وسبعين ألفاً، ومعها ٣٢٢٥ في
 * الطابور — وابتلع الطابورُ رسائلَ اليوم عن رسائل اليوم.
 *
 * ═══ ولماذا لم يمسكه اختبار ═══
 *
 * حمولاتُ الاختبار كلُّها مكتوبةٌ بمعرّفات Meta القصيرة. والطولُ
 * الحقيقيّ لا يأتي إلا من جسرٍ حيّ — فيُكتب هنا كما يرسله.
 */
class WhatsAppMediaOverflowTest extends TestCase
{
    use RefreshDatabase;

    /** عنوانُ وسيطٍ كما يرسله الجسر فعلاً — لا معرّفٌ قصير. */
    private function bridgeUrl(): string
    {
        return 'https://mmg.whatsapp.net/v/t62.7118-24/'
            . str_repeat('0123456789', 12)
            . '.enc?ccb=11-4&oh=' . str_repeat('a', 64)
            . '&oe=' . str_repeat('b', 8)
            . '&_nc_sid=' . str_repeat('c', 32)
            . '&mms3=true';
    }

    /**
     * حدثٌ عمرُه نصفُ ساعة.
     *
     * ‏created_at داخل create() يدهسه الطابعُ الزمنيّ التلقائيّ، فيخرج
     * الحدثُ «الآن» ولا تمسّه المكنسةُ التي تشترط خمس دقائق.
     */
    private function agedEvent(string $prefix): WhatsAppWebhookEvent
    {
        $event = WhatsAppWebhookEvent::create([
            'event_key' => $prefix . '-' . bin2hex(random_bytes(6)),
            'kind' => 'message',
            'payload' => [],
        ]);

        $event->forceFill(['created_at' => now()->subMinutes(30)])->save();

        return $event->fresh();
    }

    /** الرسالةُ الواردة كما يبنيها المحلّل. */
    private function inbound(string $mediaId): array
    {
        return [
            'id' => 'wamid.' . bin2hex(random_bytes(8)),
            'from' => '96891234567',
            'timestamp' => (string) now()->timestamp,
            'type' => 'image',
            'image' => ['id' => $mediaId, 'mime_type' => 'image/jpeg', 'caption' => 'صورة'],
        ];
    }

    /** العنوانُ الطويل يُحفظ كاملاً — والقصُّ إتلافٌ لا حلّ. */
    public function test_a_bridge_url_is_stored_whole(): void
    {
        $url = $this->bridgeUrl();
        $this->assertGreaterThan(160, strlen($url), 'العنوانُ المُختبَر أقصرُ من العمود القديم');

        app(InboxService::class)->ingestIncoming($this->inbound($url), []);

        $saved = WhatsAppMessage::where('direction', WhatsAppMessage::IN)->latest('id')->first();

        $this->assertNotNull($saved, 'الرسالةُ لم تُحفظ أصلاً');
        $this->assertSame($url, $saved->media_id, 'العنوانُ حُفظ مقصوصاً — فلا يُجلب به الملفّ');
    }

    /**
     * ═══ وعطبُ شكلٍ لا يُعاد إلى الأبد ═══
     *
     * هذا هو ما حوّل عطباً واحداً إلى ثلاثةٍ وسبعين ألفاً: المهمّةُ
     * تُرمى فتُعاد خمساً، والمكنسةُ تعيد دفعَ الحدث كلَّ خمس دقائق.
     */
    public function test_a_permanent_data_error_is_not_retried(): void
    {
        $event = WhatsAppWebhookEvent::create([
            'event_key' => 'k-' . bin2hex(random_bytes(8)),
            'kind' => 'message',
            'payload' => ['messages' => [['broken' => true]]],
        ]);

        // استثناءُ الاستعلام يحمل SQLSTATE في errorInfo — يُبنى كما يبنيه PDO
        $permanent = new \Illuminate\Database\QueryException(
            'mysql', 'insert into `whatsapp_messages`', [],
            tap(new \PDOException("SQLSTATE[22001]: String data, right truncated"), function ($e) {
                $e->errorInfo = ['22001', 1406, 'Data too long for column \'media_id\' at row 1'];
            }),
        );

        $job = new ProcessWhatsAppWebhook($event->id);

        $reflection = new \ReflectionMethod($job, 'permanent');
        $reflection->setAccessible(true);

        $this->assertTrue($reflection->invoke(null, $permanent),
            'عطبُ الطول عُدّ عارضاً يزول — فيُعاد إلى الأبد');

        // وانقطاعُ الاتّصال عارضٌ يزول: يُعاد
        $transient = new \Illuminate\Database\QueryException(
            'mysql', 'select 1', [],
            tap(new \PDOException('server has gone away'), function ($e) {
                $e->errorInfo = ['HY000', 2006, 'MySQL server has gone away'];
            }),
        );

        $this->assertFalse($reflection->invoke(null, $transient),
            'انقطاعُ الاتّصال عُدّ عطباً دائماً — فتضيع رسالةٌ كان يمكن استدراكُها');
    }

    /** والحدثُ المستنفَد لا تعيد المكنسةُ دفعَه. */
    public function test_the_sweep_stops_redispatching_an_exhausted_event(): void
    {
        Queue::fake();

        $doomed = $this->agedEvent('doomed');
        $fresh = $this->agedEvent('fresh');

        for ($i = 0; $i < WhatsAppWebhookEvent::MAX_ATTEMPTS; $i++) {
            $doomed->markFailed('Data too long for column \'media_id\'');
        }

        $this->assertTrue($doomed->fresh()->exhausted());

        $this->artisan('whatsapp:sweep')->assertExitCode(0);

        Queue::assertPushed(ProcessWhatsAppWebhook::class, fn ($job) => $job->eventId === $fresh->id);
        Queue::assertNotPushed(ProcessWhatsAppWebhook::class, fn ($job) => $job->eventId === $doomed->id);
    }

    /** والمستنفَدُ يبقى على القرص: هو رسالةُ موكّلٍ لم تُقرأ، لا يُحذف. */
    public function test_an_exhausted_event_is_kept_not_deleted(): void
    {
        $event = $this->agedEvent('keep');

        for ($i = 0; $i < WhatsAppWebhookEvent::MAX_ATTEMPTS + 3; $i++) {
            $event->markFailed('boom');
        }

        $this->artisan('whatsapp:sweep')->assertExitCode(0);

        $this->assertDatabaseHas('whatsapp_webhook_events', ['id' => $event->id]);
    }
}
