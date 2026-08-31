<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWhatsAppWebhook;
use App\Models\WhatsAppWebhookEvent;
use App\Support\WhatsAppSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * بابُ واتساب إلى هذا المكتب.
 *
 * ═══ العزل ═══
 *
 * كلُّ مكتب في مُداوَلة نسخةُ تطبيقٍ مستقلّة بنطاقها وقاعدتها. فهذا
 * المسار يعيش على نطاق هذا المكتب وحده، ولا يصل إليه إشعارُ مكتبٍ
 * آخر أصلاً — لا لأنّ استعلاماً يُرشِّح، بل لأنّ العنوان مختلف
 * والقاعدة مختلفة. ومع ذلك يُتحقَّق من معرّف الرقم في الحمولة: نطاقٌ
 * صحيحٌ بحمولةِ رقمٍ ليس رقمَ هذا المكتب لا تُقبل.
 *
 * ═══ لماذا لا يُعالَج هنا ═══
 *
 * تُعيد Meta الإرسال إن تأخّر الردُّ عن ثوانٍ. وتنزيلُ صورةٍ أو سؤالُ
 * الذكاء الاصطناعي يستغرق أضعافَ ذلك — فيصل الإشعارُ مرّتين وثلاثاً.
 * فالمطلوبُ هنا: تحقَّق، قيِّد، أعِد 200، وأحِل الباقي إلى الطابور.
 */
class WhatsAppWebhookController extends Controller
{
    /**
     * مصافحةُ التحقّق من Meta — طلب GET يحمل تحدّياً يُعاد كما هو.
     *
     * التسميةُ «challenge» لا «show» ولا «index» مقصودة: هذا ليس
     * عرضَ مورد، وأيُّ اسمٍ يوحي بذلك يُربك مراجعةَ المسارات.
     */
    public function challenge(Request $request): Response
    {
        $mode = (string) $request->query('hub_mode', $request->query('hub.mode', ''));
        $token = (string) $request->query('hub_verify_token', $request->query('hub.verify_token', ''));
        $challenge = (string) $request->query('hub_challenge', $request->query('hub.challenge', ''));

        $expected = WhatsAppSettings::verifyToken();

        // hash_equals لا «===»: المقارنةُ العادية تتوقّف عند أوّل حرفٍ
        // مختلف، وزمنُ توقّفها يُسرّب طولَ البادئة الصحيحة لمن يقيس.
        if ($mode === 'subscribe' && $token !== '' && hash_equals($expected, $token)) {
            WhatsAppSettings::touchWebhook();

            // نصٌّ صِرف: Meta تقارن الجسم حرفياً بالتحدّي الذي أرسلته
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('WhatsApp webhook verification refused from ' . $request->ip());

        return response('', 403);
    }

    /** استقبالُ الإشعارات. */
    public function receive(Request $request): Response
    {
        $raw = $request->getContent();

        if (!$this->signatureValid($request, $raw)) {
            Log::warning('WhatsApp webhook signature rejected from ' . $request->ip());

            // ٤٠٣ لا ٤٠٠: طلبٌ لم يُثبت أنّه من Meta لا يُقرأ محتواه
            return response('', 403);
        }

        $payload = json_decode($raw, true);

        if (!is_array($payload) || ($payload['object'] ?? null) !== 'whatsapp_business_account') {
            return response('', 200); // نوعٌ لا يخصّنا — لا يُعاد إرساله
        }

        WhatsAppSettings::touchWebhook();

        foreach ($this->events($payload) as $event) {
            $this->enqueue($event);
        }

        // ٢٠٠ دائماً بعد التحقّق: أيُّ رمزٍ آخر يجعل Meta تُعيد الإرسال
        // بلا نهاية على حدثٍ قد يكون معطوباً أصلاً
        return response('', 200);
    }

    /**
     * التحقّق من توقيع Meta — HMAC-SHA256 على الجسم الخام بسرّ التطبيق.
     *
     * ═══ لماذا الجسم الخام ═══
     *
     * إعادةُ ترميز JSON تُغيّر مسافةً أو ترتيبَ مفتاحٍ فيختلف التوقيع،
     * فتُرفض إشعاراتٌ صحيحة. التوقيعُ على البايتات كما وصلت لا على ما
     * فهمناه منها.
     *
     * وبلا سرِّ تطبيقٍ مضبوط: يُرفض الطلب. القبولُ بلا تحقّق يعني أنّ
     * أيَّ جهةٍ تعرف العنوان تستطيع حقنَ رسائل في محادثات المكتب.
     */
    protected function signatureValid(Request $request, string $raw): bool
    {
        $secret = WhatsAppSettings::appSecret();

        if (!filled($secret)) {
            return false;
        }

        $header = (string) $request->header('X-Hub-Signature-256', '');

        if (!str_starts_with($header, 'sha256=')) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $raw, $secret);

        return hash_equals($expected, $header);
    }

    /**
     * تفكيكُ الحمولة إلى أحداثٍ مستقلّة، كلٌّ بمفتاحٍ لا يتكرّر.
     *
     * المفتاح مشتقٌّ من المحتوى لا من زمن الوصول: الإعادةُ تحمل نفس
     * المفتاح فتُرفض عند القيد. ولحالات التسليم يدخل الحالةُ في
     * المفتاح — فالرسالة الواحدة تمرّ بـsent ثم delivered ثم read،
     * وثلاثتُها أحداثٌ مشروعة لا تكرار.
     *
     * @return array<int, array{key: string, kind: string, data: array}>
     */
    protected function events(array $payload): array
    {
        $out = [];
        $ourPhoneId = WhatsAppSettings::phoneNumberId();

        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                $value = (array) ($change['value'] ?? []);
                $phoneId = (string) ($value['metadata']['phone_number_id'] ?? '');

                // حمولةٌ لرقمٍ ليس رقمَ هذا المكتب تُطرح ولا تُخزَّن.
                // لا تقع في التنصيب العادي، لكنّها الفرقُ بين «مستحيلٌ
                // بحكم البنية» و«مرفوضٌ صراحةً» يوم يتغيّر التنصيب.
                if ($ourPhoneId !== null && $phoneId !== '' && $phoneId !== $ourPhoneId) {
                    Log::warning('WhatsApp webhook for a foreign phone_number_id — refused.');

                    continue;
                }

                $contacts = (array) ($value['contacts'] ?? []);

                foreach ((array) ($value['messages'] ?? []) as $message) {
                    $wamid = (string) ($message['id'] ?? '');

                    if ($wamid === '') {
                        continue;
                    }

                    $out[] = [
                        'key' => 'msg:' . $wamid,
                        'kind' => 'message',
                        'data' => ['message' => $message, 'contacts' => $contacts, 'phone_number_id' => $phoneId],
                    ];
                }

                foreach ((array) ($value['statuses'] ?? []) as $status) {
                    $wamid = (string) ($status['id'] ?? '');
                    $state = (string) ($status['status'] ?? '');

                    if ($wamid === '' || $state === '') {
                        continue;
                    }

                    $out[] = [
                        'key' => 'st:' . $wamid . ':' . $state,
                        'kind' => 'status',
                        'data' => ['status' => $status],
                    ];
                }
            }
        }

        return $out;
    }

    /**
     * قيدُ الحدث ثم إحالتُه.
     *
     * القيدُ أوّلاً والدفعُ بعده: لو انعكس الترتيب لعُولج حدثٌ لم
     * يُقيَّد بعد، فتقبله إعادةُ Meta مرّةً ثانية.
     */
    protected function enqueue(array $event): void
    {
        try {
            $row = WhatsAppWebhookEvent::create([
                'event_key' => $event['key'],
                'kind' => $event['kind'],
                'payload' => $event['data'],
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // قيدُ التفرّد رفضه: حدثٌ رأيناه من قبل. هذا هو المسار
            // الطبيعي لإعادات Meta، فلا يُسجَّل خطأً ولا يُنبَّه عليه.
            if ($this->isDuplicate($e)) {
                return;
            }

            throw $e;
        }

        try {
            ProcessWhatsAppWebhook::dispatch($row->id);
        } catch (\Throwable $e) {
            // طابورٌ لا يستقبل: الحدث مقيَّدٌ على القرص ولم يضِع.
            // يلتقطه أمرُ الاستدراك المجدوَل بدل أن يُفقد.
            Log::error('WhatsApp webhook dispatch failed: ' . $e->getMessage());
        }
    }

    private function isDuplicate(\Illuminate\Database\QueryException $e): bool
    {
        // 23000 هو رمز انتهاك القيد في MySQL وSQLite معاً
        return ($e->errorInfo[0] ?? null) === '23000'
            || str_contains(strtolower($e->getMessage()), 'unique');
    }
}
