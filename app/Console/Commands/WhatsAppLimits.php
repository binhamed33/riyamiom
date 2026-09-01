<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\SendingGuard;
use App\Support\ClientEvents;
use App\Support\WhatsAppSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * حدودُ الإرسال كما هي فعلاً على هذا المكتب — لا كما في الكود.
 *
 * ═══ العطل الذي يمنعه ═══
 *
 * الحدودُ لها مصدران: افتراضٌ في الكود، وصفٌّ مخزَّنٌ في جدول
 * الإعدادات. والمخزَّنُ يغلب. فرفعُ السقف في الكود إلى مئةٍ لا يغيّر
 * شيئاً في مكتبٍ حُفظت إعداداتُه مرّةً وفيه خمسون — والشاشةُ تقول
 * «مئة» لأنّها تقرأ افتراضَ الكود حين يتعذّر عليها الصفّ.
 *
 * فلا يُعرف السقفُ الحقيقيُّ لمكتبٍ إلا بسؤال المكتب نفسِه. وهذا ما
 * يفعله هذا الأمر: يقرأ القيمةَ النافذة، ويقول من أين جاءت — مخزَّنةً
 * أم افتراضاً — ويعلن ما خالف السياسة المعتمَدة.
 *
 * ═══ ولماذا سقفان لا سقف ═══
 *
 * لأنّ التدرّج بعد أوّل اقتران يخفض السقفَ النافذَ أيّاماً: مكتبٌ
 * سقفُه مئةٌ واقترن أمسِ سقفُه اليومَ ثمانيةٌ وعشرون — وهذا صحيح
 * مقصود، لكنّه يبدو عطلاً لمن يقرأ رقماً واحداً. فيُعرض الاثنان:
 * المضبوط، والنافذُ اليوم.
 *
 * ولا يكتب شيئاً إلا بـ‎--fix، وحينها يكتب صفوفَ إعداداتٍ لا غير:
 * لا رسالةَ ولا محادثةَ ولا بياناتِ موكّل.
 */
class WhatsAppLimits extends Command
{
    protected $signature = 'whatsapp:limits
        {--json : سطرٌ واحد لأدوات الفحص الجماعي}
        {--fix : إعادةُ ما خالف السياسة إلى قيمته المعتمَدة}';

    protected $description = 'حدود إرسال واتساب النافذة على هذا المكتب، ومطابقتُها للسياسة';

    /**
     * السياسةُ المعتمَدة — مصدرُها افتراضاتُ الحارس نفسِها.
     *
     * ولا تُكتب الأرقامُ هنا مرّةً ثانية: نسختان من الرقم تفترقان في
     * أوّل تعديل، فيقول الفحصُ «مخالف» عن مكتبٍ مضبوط.
     *
     * @return array<string, string>
     */
    private function policy(): array
    {
        return [
            SendingGuard::KEY_ENABLED => '1',
            SendingGuard::KEY_CLIENTS_ONLY => '1',
            SendingGuard::KEY_PER_DAY => (string) SendingGuard::DEFAULT_PER_DAY,
            SendingGuard::KEY_PER_HOUR => (string) SendingGuard::DEFAULT_PER_HOUR,
            SendingGuard::KEY_MIN_GAP => (string) SendingGuard::DEFAULT_MIN_GAP,
            SendingGuard::KEY_QUIET_FROM => (string) SendingGuard::DEFAULT_QUIET_FROM,
            SendingGuard::KEY_QUIET_TO => (string) SendingGuard::DEFAULT_QUIET_TO,
            WhatsAppSettings::KEY_INBOX_VISIBLE => '0',
        ];
    }

    public function handle(): int
    {
        if (! Schema::hasTable('settings')) {
            $this->error('جدول الإعدادات غير مهاجَر — php artisan migrate --force');

            return self::FAILURE;
        }

        $report = $this->report();

        if ($this->option('fix') && $report['drift'] !== []) {
            foreach ($report['drift'] as $key => $pair) {
                Setting::set($key, $pair['should'], SendingGuard::GROUP);
                $this->line('  أُعيد ' . $key . ': ' . $pair['is'] . ' ← ' . $pair['should']);
            }

            $report = $this->report();
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_UNESCAPED_UNICODE));

            return $report['policy_ok'] ? self::SUCCESS : self::FAILURE;
        }

        $this->render($report);

        return $report['policy_ok'] ? self::SUCCESS : self::FAILURE;
    }

    /** @return array<string, mixed> */
    private function report(): array
    {
        $drift = [];

        foreach ($this->policy() as $key => $should) {
            // الغائبُ ليس مخالفاً: لا صفَّ يعني أنّ الكودَ هو المصدر،
            // وافتراضُ الكود هو السياسةُ نفسُها. وإعلانُه مخالفةً
            // يجعل كلَّ مكتبٍ جديدٍ يخرج «أحمر» في أوّل فحص.
            $is = Setting::get($key);

            if ($is !== null && $is !== '' && (string) $is !== $should) {
                $drift[$key] = ['is' => (string) $is, 'should' => $should];
            }
        }

        $storedDay = Setting::get(SendingGuard::KEY_PER_DAY);
        $effective = SendingGuard::perDay();

        $held = Schema::hasTable('whatsapp_messages')
            ? WhatsAppMessage::whereNotNull('hold_until')
                ->where('status', WhatsAppMessage::STATUS_QUEUED)
                ->count()
            : 0;

        return [
            'domain' => (string) parse_url((string) config('app.url'), PHP_URL_HOST),
            'guard_enabled' => SendingGuard::enabled(),
            'clients_only' => SendingGuard::clientsOnly(),
            'inbox_visible' => WhatsAppSettings::inboxVisible(),
            'per_day' => (int) ($storedDay ?? SendingGuard::DEFAULT_PER_DAY),
            'per_day_source' => $storedDay === null || $storedDay === '' ? 'code' : 'stored',
            'per_day_effective' => $effective,
            'warming_up' => $effective < (int) ($storedDay ?? SendingGuard::DEFAULT_PER_DAY),
            'per_hour' => SendingGuard::perHour(),
            'min_gap' => SendingGuard::minGap(),
            'quiet_from' => (int) (Setting::get(SendingGuard::KEY_QUIET_FROM) ?? SendingGuard::DEFAULT_QUIET_FROM),
            'quiet_to' => (int) (Setting::get(SendingGuard::KEY_QUIET_TO) ?? SendingGuard::DEFAULT_QUIET_TO),
            'remaining_today' => SendingGuard::remainingToday(),
            'held_now' => $held,
            'notifications_master' => ClientEvents::masterEnabled(),
            // عددُ الأنواع المشغَّلة: مفتاحٌ مضيءٌ وصفرُ أنواعٍ هي
            // بصمةُ المسح الذي كان يقع لحظةَ التشغيل — ومظهرُها
            // للمكتب «كلُّ شيءٍ مفعَّل ولا تصل رسالة»
            'notification_types_on' => count(array_filter(
                ClientEvents::types(),
                static fn (string $type): bool => ClientEvents::chosen($type),
            )),
            'provider' => (string) config('whatsapp.default', 'meta'),
            'connected' => WhatsAppSettings::isConnected(),
            'policy_ok' => $drift === [],
            'drift' => $drift,
        ];
    }

    /** @param array<string, mixed> $r */
    private function render(array $r): void
    {
        $yes = static fn (bool $v): string => $v ? '<fg=green>نعم</>' : '<fg=red>لا</>';

        $this->newLine();
        $this->line('<options=bold>حدود الإرسال — ' . ($r['domain'] ?: 'مكتب') . '</>');
        $this->line(str_repeat('─', 52));

        $this->row('الحدود مفعَّلة', $yes((bool) $r['guard_enabled']));
        $this->row('المراسلة للموكّلين وحدهم', $yes((bool) $r['clients_only']));
        $this->row('صندوق الوارد ظاهر', $r['inbox_visible'] ? '<fg=red>نعم</>' : '<fg=green>لا</>');
        $this->row('السقف اليومي المضبوط', $r['per_day']
            . ($r['per_day_source'] === 'stored' ? '  (مخزَّن)' : '  (افتراض الكود)'));

        $this->row('السقف النافذ اليوم', $r['warming_up']
            ? '<fg=yellow>' . $r['per_day_effective'] . '</>  (تدرُّجُ ما بعد الاقتران)'
            : (string) $r['per_day_effective']);

        $this->row('السقف الساعي', (string) $r['per_hour']);
        $this->row('المهلة بين رسالتين', $r['min_gap'] . ' ثانية');
        $this->row('ساعات الصمت', $r['quiet_from'] . ' ← ' . $r['quiet_to']);
        $this->row('المتبقّي اليوم', (string) $r['remaining_today']);

        if ((int) $r['held_now'] > 0) {
            $this->row('محجوزةٌ تنتظر وقتَها', '<fg=yellow>' . $r['held_now'] . '</>');
        }

        $this->row('إشعارات الموكّل', $r['notifications_master']
            ? '<fg=green>مشغّلة</>'
            : '<fg=yellow>مطفأة — لا يصل الموكّلَ شيء</>');

        $this->row('أنواعٌ مشغَّلة', $r['notifications_master'] && (int) $r['notification_types_on'] === 0
            ? '<fg=red>لا شيء — مفتاحٌ مضيءٌ بلا نوعٍ واحد</>'
            : (string) $r['notification_types_on']);

        $this->row('الرقم مربوط', $yes((bool) $r['connected']));

        $this->newLine();

        if ($r['policy_ok']) {
            $this->line('<fg=green>مطابقٌ للسياسة المعتمَدة.</>');

            return;
        }

        $this->line('<fg=red>مخالفٌ للسياسة:</>');

        foreach ($r['drift'] as $key => $pair) {
            $this->line('  ' . $this->pad($key, 24) . ': ' . $pair['is'] . '  ← يجب ' . $pair['should']);
        }

        $this->newLine();
        $this->line('للإعادة إلى المعتمَد: php artisan whatsapp:limits --fix');
    }

    private function row(string $label, string $value): void
    {
        $this->line('  ' . $this->pad($label, 26) . ': ' . $value);
    }

    /** حشوٌ بالمحارف لا بالبايتات — الحرفُ العربي ثلاثةُ بايتات. */
    private function pad(string $text, int $width): string
    {
        $length = mb_strlen($text);

        return $length >= $width ? $text . ' ' : $text . str_repeat(' ', $width - $length);
    }
}
