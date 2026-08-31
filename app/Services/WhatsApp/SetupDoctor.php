<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Support\WhatsAppSettings;

/**
 * أين توقّف المكتب في ربط واتساب — وما الذي يفعله الآن.
 *
 * ═══ العطل الذي يمنعه ═══
 *
 * الربطُ خمسُ خطوات، ثلاثٌ منها عند Meta لا عندنا. وحين لا تصل رسالة
 * لا يعرف صاحبُ المكتب أيَّ خطوةٍ سقطت: أالرمزُ خطأ؟ أم العنوانُ لم
 * يُسجَّل؟ أم سُجّل ولم يُشترَك في الحقول؟ فيعيد الخمسَ كلَّها ويعيدها،
 * وكلُّها تبدو صحيحةً في لوحة Meta.
 *
 * فيُسأل كلُّ شرطٍ على حدة، ويُقال بالضبط: هذه تمّت، وهنا وقفت، وهذا
 * ما ينقصها. وثلاثةٌ من الأسئلة تُوجَّه إلى Meta نفسها لا إلى ما
 * خزّنّاه — لأنّ ما خزّنّاه يقول ما أدخله المكتب، لا ما قبلته Meta.
 *
 * ═══ ولماذا خطوةٌ واحدةٌ «التالية» ═══
 *
 * لو عُرضت الخطواتُ المتعثّرة كلُّها لعاد التشتّت الذي وُضع المعالج
 * لرفعه: خطوةٌ لا تصحّ إلا بعد سابقتها تُعرض متعثّرةً بجانبها فتبدو
 * عطلاً ثانياً. فالخطواتُ بترتيبها، وأوّلُ ما لم يتمّ هو «التالي»،
 * وما بعده ينتظر.
 */
class SetupDoctor
{
    public const DONE = 'done';
    public const NEXT = 'next';
    public const WAITING = 'waiting';

    /** الحقول التي بلا اشتراكٍ فيها لا يصل شيء. */
    public const REQUIRED_FIELDS = ['messages'];

    /** حقلٌ مستحبٌّ لا يمنع الاستقبال — حالةُ القوالب تصل به. */
    public const NICE_FIELDS = ['message_template_status_update'];

    /**
     * الخطواتُ بترتيبها، ولكلٍّ حالتُها وسببُها.
     *
     * @param bool $probe هل يُسأل Meta فعلاً؟ التحميلُ العادي للصفحة
     *                    لا يسأل: ثلاثةُ نداءاتٍ شبكيّة في كلّ فتحةِ
     *                    صفحةٍ تُبطئها وتستهلك حصّةَ الطلبات بلا داعٍ.
     *                    الفحصُ بضغطةٍ من المستخدم يسأل.
     * @return array{steps: array<int, array<string, mixed>>, ready: bool, next: ?string}
     */
    public function report(bool $probe = false): array
    {
        // خطواتُ Meta لا معنى لها على الجسر: لا رمزَ ولا سرَّ تطبيق
        // ولا اشتراكَ حقول. وعرضُها متعثّرةً هناك يُوهم المكتبَ أنّ
        // عنده أربعةَ أعطالٍ وهو موصولٌ يعمل.
        if (WhatsAppSettings::usingEvolution()) {
            return $this->bridgeReport($probe);
        }

        $steps = [
            $this->credentials(),
            $this->reachable($probe),
            $this->webhookRegistered(),
            $this->fieldsSubscribed($probe),
            $this->firstMessage(),
            $this->templates(),
        ];

        // أوّلُ ما لم يتمّ هو التالي، وما بعده ينتظر
        $foundNext = false;

        foreach ($steps as $i => $step) {
            if ($step['state'] === self::DONE) {
                continue;
            }

            if (!$foundNext) {
                $foundNext = true;
                continue;
            }

            $steps[$i]['state'] = self::WAITING;
            $steps[$i]['reason'] = 'تنتظر إتمام ما قبلها.';
        }

        $required = array_filter($steps, static fn (array $s): bool => (bool) ($s['required'] ?? false));

        return [
            'steps' => array_values($steps),
            'probed' => $probe,
            'ready' => array_reduce(
                $required,
                static fn (bool $carry, array $s): bool => $carry && $s['state'] === self::DONE,
                true,
            ),
            'next' => $this->firstIncomplete($steps),
        ];
    }

    /**
     * خطواتُ الجسر — ثلاثٌ لا ستّ.
     *
     * @return array{steps: array<int, array<string, mixed>>, ready: bool, next: ?string}
     */
    private function bridgeReport(bool $probe): array
    {
        $configured = filled(config('whatsapp.evolution.base_url')) && filled(config('whatsapp.evolution.api_key'));
        $state = WhatsAppSettings::evolutionState();

        if ($probe) {
            $provider = WhatsAppManager::make();

            if ($provider instanceof EvolutionProvider) {
                $state = $provider->connectionState();
                WhatsAppSettings::setEvolutionState($state);
            }
        }

        $steps = [
            $this->step(
                key: 'bridge_server',
                title: 'خادم الجسر يعمل',
                where: 'على هذا الخادم',
                done: $configured,
                reason: $configured
                    ? 'العنوان والمفتاح مضبوطان.'
                    : 'لم يُضبط EVOLUTION_BASE_URL أو EVOLUTION_API_KEY في ملفّ بيئة المكتب.',
                action: 'شغّل سكربت التنصيب على الخادم ثمّ أعد الفحص.',
            ),
            $this->step(
                key: 'bridge_paired',
                title: 'الرقم مقترن',
                where: 'واتساب ← الأجهزة المرتبطة',
                done: $state === 'open',
                reason: match ($state) {
                    'open' => 'الرقم مقترنٌ ويرسل.',
                    'connecting' => 'النسخة تنتظر مسحَ الرمز.',
                    default => 'لا اقترانَ بعد.',
                },
                action: 'اضغط «ابدأ الاقتران» وامسح الرمز من هاتف المكتب.',
            ),
            $this->firstMessage(),
        ];

        $found = false;

        foreach ($steps as $i => $step) {
            if ($step['state'] === self::DONE) {
                continue;
            }

            if (!$found) {
                $found = true;
                continue;
            }

            $steps[$i]['state'] = self::WAITING;
            $steps[$i]['reason'] = 'تنتظر إتمام ما قبلها.';
        }

        return [
            'steps' => $steps,
            'probed' => $probe,
            'ready' => $configured && $state === 'open',
            'next' => $this->firstIncomplete($steps),
        ];
    }

    // ── الخطوات ──────────────────────────────────────────────────

    private function credentials(): array
    {
        $missing = [];

        if (!filled(WhatsAppSettings::accessToken())) {
            $missing[] = 'رمز الوصول الدائم';
        }

        if (!filled(WhatsAppSettings::phoneNumberId())) {
            $missing[] = 'معرّف الرقم';
        }

        if (!filled(WhatsAppSettings::wabaId())) {
            $missing[] = 'معرّف حساب الأعمال';
        }

        // سرُّ التطبيق ليس للإرسال بل للاستقبال: بدونه تُرفض كلُّ
        // حمولةٍ واردة لأنّ توقيعها لا يُتحقَّق منه — فهو شرطٌ لا تحسين
        if (!filled(WhatsAppSettings::appSecret())) {
            $missing[] = 'سرّ التطبيق';
        }

        return $this->step(
            key: 'credentials',
            title: 'بيانات Meta الأربعة',
            where: 'في هذه الصفحة',
            done: $missing === [],
            reason: $missing === []
                ? 'الأربعة محفوظة.'
                : 'ينقص: ' . implode('، ' , $missing) . '. تجدها في لوحة Meta تحت WhatsApp ← API Setup، وسرّ التطبيق تحت Settings ← Basic.',
            action: 'املأ الحقول أدناه واضغط «حفظ».',
        );
    }

    private function reachable(bool $probe): array
    {
        if (!filled(WhatsAppSettings::accessToken()) || !filled(WhatsAppSettings::phoneNumberId())) {
            return $this->step(
                key: 'reachable',
                title: 'Meta تقبل الرمز',
                where: 'فحصٌ حيّ',
                done: false,
                reason: 'لا يُسأل قبل حفظ الرمز ومعرّف الرقم.',
                action: 'أتمّ الخطوة السابقة.',
            );
        }

        // بلا فحصٍ حيّ: ما خزّنّاه من هويّة الرقم دليلُ نجاحٍ سابق —
        // فلا يُعاد النداءُ في كل فتحةِ صفحة
        if (!$probe) {
            $known = filled(WhatsAppSettings::displayPhone());

            return $this->step(
                key: 'reachable',
                title: 'Meta تقبل الرمز',
                where: 'فحصٌ حيّ',
                done: $known,
                reason: $known
                    ? 'الرقم المعروف: ' . WhatsAppSettings::displayPhone()
                    : 'لم يُجرَّب بعد — اضغط «افحص الآن».',
                action: 'اضغط «افحص الآن» ليُسأل Meta.',
            );
        }

        $provider = WhatsAppManager::make();
        $result = $provider ? $provider->testConnection() : ['ok' => false, 'message' => 'المزوّد غير متاح.'];

        $ok = (bool) ($result['ok'] ?? false);
        $name = (string) ($result['verified_name'] ?? '');
        $success = 'الرقم: ' . (string) ($result['display_phone_number'] ?? '—')
            . ($name !== '' ? ' — ' . $name : '');

        return $this->step(
            key: 'reachable',
            title: 'Meta تقبل الرمز',
            where: 'فحصٌ حيّ',
            done: $ok,
            reason: $success,
            fallbackReason: (string) ($result['message'] ?? 'تعذّر الاتصال.'),
            action: 'راجع الرمز ومعرّف الرقم: أكثرُ ما يقع أن يُنسخ رمزُ الاختبار المؤقّت (ينتهي بعد ٢٤ ساعة) بدل الرمز الدائم من System User.',
        );
    }

    private function webhookRegistered(): array
    {
        $seen = WhatsAppSettings::snapshot()['last_webhook_at'] ?? null;

        return $this->step(
            key: 'webhook',
            title: 'تسجيل عنوان الويبهوك عند Meta',
            where: 'WhatsApp ← Configuration ← Webhook ← Edit',
            done: filled($seen),
            reason: filled($seen)
                ? 'وصلَنا إشعارٌ من Meta — آخره ' . $seen
                : 'لم يصل من Meta إشعارٌ واحد قطّ. والعنوانُ لا يُسجَّل من عندنا: Meta هي التي تناديه.',
            action: 'الصق رابط الويبهوك ورمز التحقّق أدناه في لوحة Meta ثمّ اضغط Verify and save.',
        );
    }

    private function fieldsSubscribed(bool $probe): array
    {
        if (!$probe) {
            return $this->step(
                key: 'fields',
                title: 'الاشتراك في حقل messages',
                where: 'WhatsApp ← Configuration ← Webhook fields ← Manage',
                done: false,
                reason: 'يُسأل Meta عند الفحص — اضغط «افحص الآن».',
                action: 'اضغط «افحص الآن».',
                neutral: true,
            );
        }

        $provider = WhatsAppManager::make();
        $fields = $provider?->subscribedFields();

        if ($fields === null) {
            return $this->step(
                key: 'fields',
                title: 'الاشتراك في حقل messages',
                where: 'WhatsApp ← Configuration ← Webhook fields ← Manage',
                done: false,
                reason: $provider?->getLastError() ?: 'تعذّر سؤال Meta عن الاشتراكات.',
                action: 'أتمّ معرّف حساب الأعمال والرمز أوّلاً.',
            );
        }

        $missing = array_values(array_diff(self::REQUIRED_FIELDS, $fields));
        $missingNice = array_values(array_diff(self::NICE_FIELDS, $fields));

        $note = $missingNice === []
            ? ''
            : ' (ويُستحسن الاشتراك في ' . implode('، ', $missingNice) . ' لتصل حالةُ القوالب تلقائياً)';

        return $this->step(
            key: 'fields',
            title: 'الاشتراك في حقل messages',
            where: 'WhatsApp ← Configuration ← Webhook fields ← Manage',
            done: $missing === [],
            reason: $this->fieldsReason($fields, $missing, $note),
            action: 'في نفس صفحة Configuration، عند Webhook fields اضغط Manage واشترك في messages.',
        );
    }

    private function firstMessage(): array
    {
        $count = 0;

        try {
            $count = WhatsAppMessage::where('direction', WhatsAppMessage::IN)->count();
        } catch (\Throwable) {
            // جدولٌ غير مهاجَر بعد — تُعدّ صفراً ولا تُسقط الصفحة
        }

        return $this->step(
            key: 'first_message',
            title: 'أوّل رسالةٍ واردة',
            where: 'من هاتفك إلى رقم المكتب',
            done: $count > 0,
            reason: $count > 0
                ? 'وصلت ' . $count . ' رسالة واردة.'
                : 'لم تصل رسالةٌ واردة بعد.',
            action: 'أرسل «مرحبا» من هاتفك الشخصي إلى رقم المكتب، ثمّ افحص ثانيةً — تظهر خلال ثوانٍ في صفحة واتساب.',
        );
    }

    /**
     * القوالب — الخطوةُ الوحيدة غيرُ اللازمة.
     *
     * الاستقبالُ والردُّ داخل نافذة الأربع والعشرين ساعة يعملان بلا
     * قالب. والقوالبُ لازمةٌ للإشعارات المُبتدَأة من المكتب وحدها،
     * ولذلك لا تمنع «جاهز».
     */
    private function templates(): array
    {
        $approved = 0;

        try {
            $approved = WhatsAppTemplate::where('status', 'APPROVED')->count();
        } catch (\Throwable) {
            // كسابقتها
        }

        return $this->step(
            key: 'templates',
            title: 'قالبٌ معتمَد (للإشعارات وحدها)',
            where: 'Meta Business ← WhatsApp Manager ← Message templates',
            done: $approved > 0,
            reason: $approved > 0
                ? $approved . ' قالباً معتمَداً.'
                : 'لا قالبَ معتمَداً بعد — والاستقبالُ والردُّ يعملان بدونه.',
            action: 'أنشئ قالباً عند Meta وانتظر اعتمادها، ثمّ اضغط «مزامنة القوالب». ولا تشغّل مفاتيح الإشعارات قبل ذلك: بلا قالبٍ معتمَد تُرفض كلُّ رسالةٍ مُبتدَأة.',
            required: false,
        );
    }

    // ── داخلي ────────────────────────────────────────────────────

    /**
     * @param array<int, string> $fields
     * @param array<int, string> $missing
     */
    private function fieldsReason(array $fields, array $missing, string $note): string
    {
        if ($missing === []) {
            return 'مشترَكٌ في: ' . implode('، ', $fields) . $note;
        }

        // «لا اشتراكَ البتّة» و«اشتراكٌ ناقص» شكويان مختلفتان: الأولى
        // تعني أنّ زرّ Manage لم يُفتح أصلاً، والثانية أنّه فُتح
        // واختير غيرُ المطلوب. والعلاجُ يختلف، فيختلف النصّ.
        if ($fields === []) {
            return 'العنوان قد يكون مسجَّلاً، لكنّ الحسابَ غيرُ مشترِكٍ في أيّ حقل'
                . ' — ولا تصل رسالةٌ واحدة بلا اشتراك.';
        }

        return 'مشترَكٌ في ' . implode('، ', $fields) . ' وينقص: ' . implode('، ', $missing);
    }

    /** @return array<string, mixed> */
    private function step(
        string $key,
        string $title,
        string $where,
        bool $done,
        string $reason,
        string $action,
        bool $required = true,
        bool $neutral = false,
        ?string $fallbackReason = null,
    ): array {
        return [
            'key' => $key,
            'title' => $title,
            'where' => $where,
            'state' => $done ? self::DONE : self::NEXT,
            'reason' => $done ? $reason : ($fallbackReason ?? $reason),
            'action' => $done ? null : $action,
            'required' => $required,
            'neutral' => $neutral,
        ];
    }

    /** @param array<int, array<string, mixed>> $steps */
    private function firstIncomplete(array $steps): ?string
    {
        foreach ($steps as $step) {
            if ($step['state'] !== self::DONE) {
                return (string) $step['key'];
            }
        }

        return null;
    }
}
