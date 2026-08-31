<?php

namespace App\Console\Commands;

use App\Jobs\SendWhatsAppMessage;
use App\Models\LegalCase;
use App\Models\Session;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\InboxService;
use App\Services\WhatsApp\WhatsAppManager;
use App\Support\WhatsAppSettings;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;

/**
 * تذكيرُ الموكّلين بجلساتهم عبر واتساب قبل موعدها بمدّةٍ يضبطها المكتب.
 *
 * ═══ العطل الذي تمنعه نافذةُ الساعة ═══
 *
 * يعمل الأمرُ كلَّ ساعة. ولو سأل «أيُّ جلسةٍ بعد أربعٍ وعشرين ساعةً
 * بالضبط؟» لما طابقت جلسةٌ واحدة أبداً — الثواني لا تلتقي. ولو سأل
 * «أيُّ جلسةٍ خلال أربعٍ وعشرين ساعة؟» لأرسل لكلّ جلسةٍ أربعاً وعشرين
 * رسالة، واحدةً في كلّ تشغيل.
 *
 * فالسؤالُ عن دلوِ ساعةٍ واحدة: الجلساتُ الواقعة في الساعة التي تبدأ
 * بعد المدّة المضبوطة. وتشغيلُ الساعة القادمة يسأل عن الدلو التالي —
 * فلا يتداخلان ولا تسقط بينهما جلسة. وتُمسح معه الساعةُ الفائتة شبكةَ
 * أمان، لأنّ المجدوَل قد يتأخّر دقائقَ فيقفز دلواً كاملاً.
 *
 * ═══ العطل الذي يمنعه حارسُ التكرار ═══
 *
 * رسالتان عن جلسةٍ واحدة ليستا إزعاجاً فحسب: البلاغُ عنهما يهبط
 * بتقييم جودة الرقم عند Meta، وقد يُقيَّد إرسالُ المكتب كلِّه. فقبل كلّ
 * إرسالٍ يُسأل خيطُ الموكّل: هل خرج منه تذكيرٌ بهذا القالب لهذه الجلسة؟
 */
class WhatsAppSessionReminders extends Command
{
    protected $signature = 'whatsapp:session-reminders {--dry : عرضُ ما كان سيُرسَل بلا إرسالٍ ولا كتابة}';

    protected $description = 'تذكير الموكّلين بجلساتهم عبر واتساب قبل موعدها بالمدّة المضبوطة';

    /** حدُّ ما يُرسَل في تشغيلٍ واحد — لا ينفجر الطابور بجلسات يومٍ مزدحم. */
    private const MAX_PER_RUN = 200;

    public function handle(InboxService $inbox): int
    {
        $dry = (bool) $this->option('dry');

        $this->newLine();
        $this->line('<options=bold>تذكير الجلسات عبر واتساب</>' . ($dry ? ' <fg=yellow>(تجربة)</>' : ''));
        $this->line(str_repeat('─', 52));

        // ── الشروطُ قبل أيّ استعلام ────────────────────────────
        if (! WhatsAppSettings::flag(WhatsAppSettings::KEY_NOTIFY_SESSIONS)) {
            $this->line('تذكير الجلسات مُطفأ في إعدادات المكتب — لا شيء يُرسَل.');

            return self::SUCCESS;
        }

        if (! WhatsAppManager::isConnected()) {
            $this->line('لم يُربط رقم واتساب لهذا المكتب بعد — لا شيء يُرسَل.');

            return self::SUCCESS;
        }

        $templateName = WhatsAppSettings::templateName(WhatsAppSettings::KEY_SESSION_TEMPLATE);

        if ($templateName === '') {
            $this->line('<fg=yellow>لم يُختَر قالبُ تذكير الجلسات</> في الإعدادات — لا شيء يُرسَل.');

            return self::SUCCESS;
        }

        // القالبُ يُقرأ بنفس استعلام SendWhatsAppMessage::templateLanguage
        // حرفاً بحرف: لو اختلفا فاخترنا هنا صفّاً واختارت المهمّةُ صفّاً
        // آخر، أُرسل القالبُ بلغةٍ لم نتحقّق من اعتمادها.
        $template = WhatsAppTemplate::where('name', $templateName)->first();

        if (! $template) {
            $this->line('<fg=yellow>القالب «' . $templateName . '» غير معروف عندنا</>'
                . ' — زامِن القوالب: php artisan whatsapp:sync-templates');

            return self::SUCCESS;
        }

        // الإرسالُ بقالبٍ غير معتمَد يُرفض بخطأ 132000/132001، فيرى
        // المحامي رسائلَ «أخفقت» بالجملة ولا يعرف أنّ العلّة عند Meta
        if (! $template->isApproved()) {
            $this->line('<fg=yellow>القالب «' . $templateName . '» حالته: ' . $template->statusLabel() . '</>'
                . ' — ولا يُرسَل إلا المعتمَد.');

            return self::SUCCESS;
        }

        // ── دلوُ الساعة ────────────────────────────────────────
        $hours = WhatsAppSettings::reminderHours();
        $anchor = now()->addHours($hours)->startOfHour();
        $from = $anchor->copy()->subHour();   // شبكةُ الأمان للساعة الفائتة
        $to = $anchor->copy()->addHour();     // نهايةُ الدلو — حصريّة

        $this->line('المدّة قبل الجلسة : ' . $hours . ' ساعة');
        $this->line('القالب            : ' . $template->name . ' (' . $template->language . ')');
        $this->line('نافذة الجلسات     : ' . $from->format('Y-m-d H:i') . ' ← ' . $to->format('Y-m-d H:i'));

        $sessions = Session::query()
            ->with(['case.client'])
            ->whereNotNull('case_id')
            // «upcoming» وحدها: المؤجَّلةُ والملغاةُ والمنتهيةُ تذكيرُها
            // خطأٌ يُربك الموكّل ويُظهر المكتبَ غافلاً عن ملفّه
            ->where('status', 'upcoming')
            ->where('date', '>=', $from)
            ->where('date', '<', $to)
            ->orderBy('date')
            ->limit(self::MAX_PER_RUN)
            ->get();

        $this->newLine();

        if ($sessions->isEmpty()) {
            $this->line('لا جلسات في هذه النافذة.');
            $this->newLine();

            return self::SUCCESS;
        }

        $rows = [];
        $queued = 0;
        $skipped = [];

        foreach ($sessions as $session) {
            $case = $session->case;
            $client = $case?->client;

            if (! $case || ! $client) {
                $this->skip($skipped, 'جلسة بلا قضية أو بلا موكّل');
                continue;
            }

            $waId = WhatsAppContact::normalizeWaId((string) $client->phone);

            // رقمٌ ناقص يُرسَل إلى Meta فيُرفض بخطأ 131026 ويُكتب «أخفق»
            // في خيط الموكّل — والعلّةُ في سجلّه لا في واتساب
            if (mb_strlen($waId) < 9) {
                $this->skip($skipped, 'موكّل بلا رقم صالح');
                continue;
            }

            // القراءةُ أوّلاً والكتابةُ بعد كلّ الحُرّاس: التجربةُ تمرّ
            // بنفس القرارات تماماً ولا تكتب صفّاً واحداً.
            //
            // ═══ ولا احتياطيَّ بـ client_id ═══
            //
            // كان هنا `?? where('client_id', $client->id)`: إن لم يوجد
            // صفٌّ لرقم الموكّل الحالي، يُؤخذ أيُّ صفٍّ مربوطٍ به. وذلك
            // صفٌّ **برقمٍ آخر** بالضرورة — وإلا لطابق الشرطَ الأوّل.
            //
            // فموكّلٌ غيّر رقمه يُرسَل تذكيرُه إلى رقمه القديم، وأرقامُ
            // عُمان تُعاد تدويرُها؛ ومن راسل المكتبَ مرّةً من رقمٍ ثمّ
            // رُبط صفُّه بموكّلٍ يتلقّى اسمَه ورقمَ قضيّته وموعدَ جلسته
            // ومحكمتَها. سرُّ المهنة لا يُخاطَر به لتوفير رسالة.
            //
            // فالرقمُ المكتوب في سجلّ الموكّل هو العنوان، وحده.
            $contact = WhatsAppContact::where('wa_id', $waId)->first();

            // الرفضُ الصريح يُحترم مهما قالت إعداداتُ المكتب — شرطُ Meta
            // وأدبُ المهنة معاً، ومخالفتُه تُبلَّغ فيهبط تقييمُ الرقم
            if ($contact && ! $contact->acceptsNotifications()) {
                $this->skip($skipped, 'طلب إيقاف المراسلة');
                continue;
            }

            $conversation = $contact
                ? WhatsAppConversation::where('contact_id', $contact->id)->first()
                : null;

            if ($conversation && $this->alreadyReminded($conversation, $templateName, $session)) {
                $this->skip($skipped, 'ذُكِّر من قبل');
                continue;
            }

            $params = $this->params($template, (string) $client->name, $case, $session);

            $rows[] = [
                (string) $client->name,
                (string) ($case->case_number ?: $case->office_case_number ?: '—'),
                $session->date instanceof CarbonInterface
                    ? $session->date->format('Y-m-d H:i')
                    : (string) $session->date,
                $waId,
                (string) count($params),
            ];

            if ($dry) {
                $queued++;
                continue;
            }

            // ── الكتابة ────────────────────────────────────────
            // جهةُ الاتصال تُنشأ إن لم تكن: أكثرُ الموكّلين لم يراسلوا
            // رقمَ المكتب قطّ. والقالبُ المعتمَد هو الطريقُ الذي أقرّته
            // Meta لبدء المحادثة من طرف المكتب — لا يشترط سابقةَ مراسلة.
            if (! $contact) {
                $contact = WhatsAppContact::create(['wa_id' => $waId, 'client_id' => $client->id]);
            } elseif ($contact->client_id === null) {
                $contact->forceFill(['client_id' => $client->id])->save();
            }

            $conversation ??= WhatsAppConversation::firstOrCreate(
                ['contact_id' => $contact->id],
                ['status' => WhatsAppConversation::STATUS_OPEN, 'unread_count' => 0]
            );

            // الربطُ بالقضية مرّةً واحدة: خيطٌ ربطه موظّفٌ بقضيّةٍ لا
            // يُنقل بقرارٍ آليّ إلى قضيّةٍ أخرى للموكّل نفسه
            if ($conversation->case_id === null) {
                $conversation->forceFill(['case_id' => $case->id])->save();
            }

            // تُكتب في الخيط أوّلاً ثمّ تُدفع للطابور: لو سقط الخادم بين
            // الأمرين بقي أثرُ التذكير ظاهراً بحالة «في الانتظار» بدل أن
            // يختفي، وحارسُ التكرار يراه فلا يُرسَل ثانيةً
            $message = $inbox->queueOutgoing(
                $conversation,
                'template',
                json_encode($params, JSON_UNESCAPED_UNICODE),
                null,
                ['template_name' => $templateName, 'session_id' => $session->id],
            );

            SendWhatsAppMessage::dispatch($message->id);
            $queued++;
        }

        if ($rows !== []) {
            $this->table(['الموكّل', 'القضية', 'موعد الجلسة', 'الرقم', 'المتغيّرات'], $rows);
        }

        $this->line($dry
            ? '<fg=yellow>تجربة:</> ' . $queued . ' تذكير كان سيُرسَل — لم يُرسَل ولم يُكتب شيء.'
            : '<fg=green>' . $queued . ' تذكير دُفع إلى الطابور.</>');

        foreach ($skipped as $reason => $n) {
            $this->line('  تُخطّي ' . $n . ' — ' . $reason);
        }

        if ($sessions->count() >= self::MAX_PER_RUN) {
            $this->line('  <fg=yellow>بلغ حدُّ التشغيل الواحد (' . self::MAX_PER_RUN . ') — شغّل الأمر ثانيةً.</>');
        }

        $this->newLine();

        return self::SUCCESS;
    }

    // ── داخلي ────────────────────────────────────────────────────

    /** @param array<string, int> $bag */
    private function skip(array &$bag, string $reason): void
    {
        $bag[$reason] = ($bag[$reason] ?? 0) + 1;
    }

    /**
     * هل خرج تذكيرٌ لهذه الجلسة بعينها من هذا الخيط؟
     *
     * ═══ لماذا بمعرّف الجلسة لا بالزمن ═══
     *
     * كان السؤالُ زمنيّاً: «هل خرج تذكيرٌ بهذا القالب في المدّة
     * الفلانية؟» — ومهما ضُبطت المدّة فهي إمّا واسعةٌ تبتلع جلسةً
     * حقيقيّة، أو ضيّقةٌ يفلت منها التكرار. وكانت أضيقُ أرضيّةٍ تمنع
     * التكرار ثلاثَ ساعات (الجلسةُ تُمسح في تشغيلين، والنافذةُ
     * ساعتان)؛ فموكّلٌ له جلستان بينهما ساعتان — وهذا يقع كثيراً:
     * قضيّتان أمام نفس المحكمة في نفس الصباح — يُذكَّر بالأولى
     * وتُبتلع الثانية. وغيابُه عنها قد يُصدر حكماً غيابيّاً.
     *
     * فصار السؤالُ عن الجلسة نفسها: خرج تذكيرُها أو لم يخرج. لا مدّةَ
     * تُضبط ولا حالةَ حدّيّة.
     *
     * والقالبُ يبقى في الشرط: مكتبٌ بدّل قالبَه يُعيد التذكير مرّةً
     * واحدة بالقالب الجديد، وهذا مقصود.
     *
     * ويبقى سقفُ الثماني والأربعين ساعة: جلسةٌ أُجّلت ثمّ أُعيد
     * جدولتُها بنفس الصفّ بعد أسبوع تستحقّ تذكيراً جديداً، ولا يمنعه
     * تذكيرُ موعدها القديم.
     */
    private function alreadyReminded(
        WhatsAppConversation $conversation,
        string $templateName,
        Session $session,
    ): bool {
        return WhatsAppMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', WhatsAppMessage::OUT)
            ->where('template_name', $templateName)
            ->where('session_id', $session->id)
            ->where('created_at', '>=', now()->subHours(48))
            ->exists();
    }

    /**
     * قيمُ متغيّرات القالب بترتيبها.
     *
     * الترتيبُ المتّفق عليه: اسمُ الموكّل، رقمُ القضية، موعدُ الجلسة،
     * مكانُها. ثمّ تُقصّ أو تُكمَّل لتطابق عددَ متغيّرات القالب كما
     * اعتمدته Meta — لأنّ اختلافَ العدد يُرفض بخطأ 132000 قبل أن تصل
     * الرسالةُ أحداً، فيرى المحامي «أخفق» بلا سبب.
     *
     * @return array<int, string>
     */
    private function params(
        WhatsAppTemplate $template,
        string $clientName,
        LegalCase $case,
        Session $session,
    ): array {
        $when = $session->date instanceof CarbonInterface
            ? $session->date->locale('ar')->isoFormat('dddd D MMMM YYYY')
                . ' الساعة ' . $session->date->format('H:i')
            : (string) $session->date;

        $values = array_map(fn (string $value) => $this->safeParam($value), [
            $clientName !== '' ? $clientName : 'موكّلنا الكريم',
            (string) ($case->case_number ?: $case->office_case_number ?: '—'),
            $when,
            (string) ($session->location ?: '—'),
        ]);

        $expected = $template->variableCount();

        // قالبٌ بلا متغيّرات يُرسَل بلا مكوّن body — وإرسالُ قيمٍ له
        // يُرفض هو الآخر
        if ($expected <= 0) {
            return [];
        }

        $values = array_slice($values, 0, $expected);

        while (count($values) < $expected) {
            $values[] = '—';
        }

        return array_values($values);
    }

    /**
     * قيمةٌ تقبلها Meta.
     *
     * تُرفض قيمةُ المتغيّر إن حملت سطراً جديداً أو مسافةً جدوليّة أو
     * أربعَ مسافاتٍ متتالية (خطأ 132000)، ومكانُ الجلسة نصٌّ حرٌّ يكتبه
     * الموظّف وقد يحمل سطرين. فتُطوى المسافاتُ كلُّها إلى واحدة.
     */
    private function safeParam(string $value): string
    {
        $clean = trim((string) preg_replace('/\s+/u', ' ', $value));

        return $clean === '' ? '—' : mb_substr($clean, 0, 900);
    }
}
