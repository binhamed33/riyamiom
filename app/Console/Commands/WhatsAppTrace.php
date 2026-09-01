<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\ClientNotification;
use App\Models\LegalCase;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\SendingGuard;
use App\Support\ClientEvents;
use App\Support\WhatsAppSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «لماذا لم تصل رسالةُ الموكّل؟» — يُجيبه النظامُ لا التخمين.
 *
 * ═══ العطل الذي يمنعه ═══
 *
 * بين فتحِ القضية ووصولِ الرسالة تسعُ حلقات: مفتاحٌ رئيسي، ونوعُ حدثٍ
 * مشغّل، ورقمُ موكّلٍ صالح، وجهةُ اتصالٍ لم تطلب الإيقاف، ورقمُ مكتبٍ
 * مربوط، وإشعارٌ يُقيَّد، ورسالةٌ تُصفّ، وحارسُ إيقاعٍ يأذن، وعاملُ
 * طابورٍ يعمل.
 *
 * وانقطاعُ أيٍّ منها يظهر للمستخدم شيئاً واحداً: «ما وصلته رسالة».
 * فيُعاد الربطُ ويُفصل الرقمُ ويُبدَّل المزوّد — والعلّةُ أنّ المفتاح
 * الرئيسي مطفأ، أو أنّ الساعة الثالثة فجراً والرسالةُ تنتظر الثامنة
 * كما أُريد لها.
 *
 * هذا الأمرُ يمشي الحلقاتِ بالترتيب ويسمّي أوّلَ مقطوعةٍ منها، ومعها
 * ما يُصلحها. ولا يكتب شيئاً ولا يرسل رسالةً: قراءةٌ محضة.
 */
class WhatsAppTrace extends Command
{
    protected $signature = 'whatsapp:trace
        {--case= : رقم القضية التي يُتوقَّع إشعارُها}
        {--client= : رقم الموكّل}
        {--type=case_created : نوع الحدث}';

    protected $description = 'تتبُّعُ سلسلة إشعار الموكّل حلقةً حلقة حتى أوّل ما انقطع';

    /** أوّلُ حلقةٍ مقطوعة — وما بعدها يُفحص للعلم لا للحكم. */
    private ?string $broken = null;

    private ?string $remedy = null;

    public function handle(): int
    {
        $this->newLine();
        $this->line('<options=bold>تتبُّع إشعار الموكّل</>');
        $this->line(str_repeat('─', 56));

        if (! Schema::hasTable('client_notifications')) {
            $this->breakAt('جداول الإشعارات غير مهاجَرة', 'php artisan migrate --force');
            $this->verdict();

            return self::FAILURE;
        }

        [$client, $case, $type] = $this->subject();

        if (! $client) {
            $this->line('<fg=yellow>لا موكّل لأتتبّعه.</> حدِّد قضيةً: php artisan whatsapp:trace --case=123');

            return self::SUCCESS;
        }

        $this->row('الموكّل', '#' . $client->id . ' — ' . (string) $client->name);
        $this->row('القضية', $case ? '#' . $case->id . ' — ' . (string) $case->case_number : '—');
        $this->row('نوع الحدث', ClientEvents::label($type) . '  (' . $type . ')');
        $this->newLine();

        // ── ١) المفتاح الرئيسي ────────────────────────────────
        $master = ClientEvents::masterEnabled();
        $this->step('المفتاح الرئيسي لإشعارات الموكّل', $master, $master ? 'مشغّل' : 'مطفأ');

        if (! $master) {
            $this->breakAt(
                'إشعاراتُ الموكّل مطفأةٌ في هذا المكتب — لا يُقيَّد إشعارٌ أصلاً',
                'الإعدادات ← إشعارات الموكّل ← فعِّل المفتاح الرئيسي',
            );
        }

        // ── ٢) نوعُ الحدث ─────────────────────────────────────
        $typeOn = ClientEvents::enabled($type);
        $this->step('نوعُ الحدث مشغّل', $typeOn, $typeOn ? 'نعم' : 'لا');

        if ($master && ! $typeOn) {
            $this->breakAt(
                'نوعُ «' . ClientEvents::label($type) . '» مطفأ',
                'الإعدادات ← إشعارات الموكّل ← أشِّر على «' . ClientEvents::label($type) . '»',
            );
        }

        // ── ٣) رقمُ الموكّل ────────────────────────────────────
        $waId = WhatsAppContact::normalizeWaId((string) $client->phone);
        $hasPhone = mb_strlen($waId) >= 9;
        $this->step('للموكّل رقمٌ صالح', $hasPhone, $hasPhone
            ? '+' . $waId . ($this->isOman($waId) ? '  (عُمان)' : '  (خارج عُمان)')
            : 'لا رقم — أو رقمٌ لا يُقرأ');

        if (! $hasPhone) {
            $this->breakAt(
                'الموكّل بلا رقمٍ صالح',
                'صفحة الموكّل ← الهاتف: 9xxxxxxx أو ‎+968 9xxxxxxx',
            );
        }

        // ── ٤) جهةُ الاتصال ───────────────────────────────────
        $contact = $hasPhone ? WhatsAppContact::where('wa_id', $waId)->first() : null;
        $accepts = ! $contact || $contact->acceptsNotifications();
        $this->step('لم يطلب الموكّل إيقافَ المراسلة', $accepts, $accepts ? 'نعم' : 'طلب الإيقاف');

        if (! $accepts) {
            $this->breakAt(
                'الموكّل طلب إيقافَ المراسلة — ورفضُه يتقدّم على كلّ إعداد',
                'لا يُتجاوَز: هو من يُلغيه برسالةٍ منه',
            );
        }

        // ── ٥) رقمُ المكتب ────────────────────────────────────
        $connected = WhatsAppSettings::isConnected();
        $this->step('رقمُ المكتب مربوط', $connected, $connected
            ? (string) config('whatsapp.providers.' . config('whatsapp.default', 'meta') . '.label', 'مربوط')
            : 'غير مربوط');

        if (! $connected) {
            $this->breakAt(
                'واتساب غير مربوطٍ في هذا المكتب — الإشعارُ يُقيَّد في البوابة ولا يُرسَل',
                'الإعدادات ← واتساب ← اربط الرقم',
            );
        }

        // ── ٦) الإشعارُ قُيّد ──────────────────────────────────
        $notification = ClientNotification::query()
            ->where('client_id', $client->id)
            ->when($case, fn ($q) => $q->where('case_id', $case->id))
            ->where('type', $type)
            ->latest('id')
            ->first();

        $this->step('الإشعارُ مقيَّد', $notification !== null, $notification
            ? '#' . $notification->id . ' — ' . $notification->created_at?->format('Y-m-d H:i')
            : 'لا إشعارَ لهذا الحدث');

        if (! $notification) {
            $this->breakAt(
                'لم يُقيَّد إشعارٌ لهذا الحدث — وقع الحدثُ قبل تشغيل المنظومة، أو النوعُ مطفأ حينها',
                'الإشعارُ لا يُقيَّد بأثرٍ رجعي: جرِّب حدثاً جديداً بعد التشغيل',
            );

            $this->queueHealth();
            $this->verdict();

            return $this->broken === null ? self::SUCCESS : self::FAILURE;
        }

        $state = (string) $notification->channel_state;
        $this->row('حالةُ القناة', match ($state) {
            ClientNotification::QUEUED => '<fg=green>صُفَّت رسالتُه</>',
            ClientNotification::SKIPPED => '<fg=yellow>تُخطّي: ' . (string) $notification->channel_reason . '</>',
            ClientNotification::FAILED => '<fg=red>أخفق: ' . (string) $notification->channel_reason . '</>',
            default => '<fg=yellow>لم تُعالَج بعد</>',
        });

        if ($state === ClientNotification::PENDING && $notification->notified_at === null) {
            $this->breakAt(
                'الإشعارُ مقيَّدٌ ولم تلتقطه المهمّة — الطابورُ لا يُصرَّف',
                'php artisan queue:work --stop-when-empty  (وراجع جدولة المُجدوِل)',
            );
        }

        if ($state === ClientNotification::SKIPPED) {
            $this->breakAt(
                'المهمّةُ تخطّت الإشعار: ' . (string) $notification->channel_reason,
                'أصلِح السببَ أعلاه ثمّ كرِّر الحدث',
            );
        }

        // ── ٧) رسالةُ واتساب ──────────────────────────────────
        $conversation = $contact
            ? WhatsAppConversation::where('contact_id', $contact->id)->first()
            : null;

        $message = $conversation
            ? WhatsAppMessage::where('conversation_id', $conversation->id)
                ->where('direction', WhatsAppMessage::OUT)
                ->where('created_at', '>=', $notification->created_at)
                ->latest('id')
                ->first()
            : null;

        $this->step('رسالةٌ صُفَّت في الصندوق', $message !== null, $message
            ? '#' . $message->id
            : 'لا رسالة');

        if (! $message) {
            $this->breakAt(
                'قُيّد الإشعارُ ولم تُصَفّ رسالة',
                'راجع السجلّ: grep "Client notification" storage/logs/laravel.log',
            );

            $this->queueHealth();
            $this->verdict();

            return self::FAILURE;
        }

        $this->row('حالةُ الرسالة', match ((string) $message->status) {
            WhatsAppMessage::STATUS_QUEUED => '<fg=yellow>في الانتظار</>',
            WhatsAppMessage::STATUS_SENT => '<fg=green>أُرسلت</>',
            WhatsAppMessage::STATUS_DELIVERED => '<fg=green>سُلّمت</>',
            WhatsAppMessage::STATUS_READ => '<fg=green>قُرئت</>',
            WhatsAppMessage::STATUS_FAILED => '<fg=red>أخفقت: ' . (string) $message->error_title . '</>',
            default => (string) $message->status,
        });

        // ── ٨) حارسُ الإيقاع ──────────────────────────────────
        //
        // ‏hold_until هو الجوابُ الذي كان غائباً: رسالةُ الثالثة فجراً
        // ليست ضائعةً ولا فاشلة — موعدُها الثامنة. ومن لا يرى الموعد
        // يظنّها ضاعت فيعيد الربطَ ويبدّل المزوّد بلا سبب.
        if ($message->hold_until !== null) {
            $this->row('محجوزةٌ حتى', '<fg=yellow>' . $message->hold_until->format('Y-m-d H:i') . '</>');
            $this->row('سببُ الحجز', $this->holdReason($message));

            $this->breakAt(
                'الرسالةُ محجوزةٌ ضمن حدود الأمان حتى ' . $message->hold_until->format('H:i')
                    . ' — وهذا عملٌ صحيح لا عطل',
                'تُطلَق تلقائياً: php artisan whatsapp:sweep  (يعمل كلَّ دقيقة بالمُجدوِل)',
            );
        }

        if ((string) $message->status === WhatsAppMessage::STATUS_FAILED) {
            $this->breakAt(
                'أخفق الإرسال: ' . (string) $message->error_title,
                'php artisan whatsapp:doctor --probe',
            );
        }

        $this->queueHealth();
        $this->verdict();

        return $this->broken === null ? self::SUCCESS : self::FAILURE;
    }

    /**
     * الموضوعُ المتتبَّع: قضيةٌ سُمّيت، أو موكّلٌ سُمّي، أو آخرُ إشعار.
     *
     * @return array{0: ?Client, 1: ?LegalCase, 2: string}
     */
    private function subject(): array
    {
        $type = (string) $this->option('type');

        if ($id = $this->option('case')) {
            $case = LegalCase::with('client')->find((int) $id);

            return [$case?->client, $case, $type];
        }

        if ($id = $this->option('client')) {
            return [Client::find((int) $id), null, $type];
        }

        $last = ClientNotification::with('client', 'case')->latest('id')->first();

        return [$last?->client, $last?->case, $last ? (string) $last->type : $type];
    }

    /** لماذا حُجزت — تُقرأ حالةُ الحارس الآن، فالسببُ لا يُخزَّن. */
    private function holdReason(WhatsAppMessage $message): string
    {
        $hour = (int) $message->hold_until?->format('H');
        $to = (int) (\App\Models\Setting::get(SendingGuard::KEY_QUIET_TO) ?? SendingGuard::DEFAULT_QUIET_TO);

        if ($hour === $to) {
            return 'ساعاتُ الصمت — لا يُوقَظ موكّلٌ برسالةٍ آليّة ليلاً';
        }

        if (SendingGuard::remainingToday() <= 0) {
            return 'بلغ المكتبُ سقفَه اليومي (' . SendingGuard::perDay() . ')';
        }

        return 'مهلةُ الإيقاع بين رسالةٍ وأخرى، أو السقفُ الساعي';
    }

    /** الطابورُ والمُجدوِل: من يحرّك كلَّ ما سبق. */
    private function queueHealth(): void
    {
        $this->newLine();
        $this->line('<options=bold>الطابور</>');

        if (Schema::hasTable('jobs')) {
            $waiting = DB::table('jobs')->count();
            $this->row('مهامٌّ تنتظر', (string) $waiting);

            $oldest = DB::table('jobs')->min('available_at');

            // مهمّةٌ تنتظر منذ ربع ساعة تعني عاملاً لا يعمل: لا شيءَ
            // ممّا فوق يصل الموكّلَ ولو كان كلُّه أخضر
            if ($oldest !== null && $oldest < now()->subMinutes(15)->getTimestamp()) {
                $this->breakAt(
                    'مهامُّ الطابور راكدةٌ منذ أكثر من ربع ساعة — العاملُ لا يصرّفها',
                    'php artisan queue:work --stop-when-empty  ثمّ تأكّد من عمل schedule:run كلَّ دقيقة',
                );
            }
        }

        if (Schema::hasTable('failed_jobs')) {
            $this->row('استُنفدت محاولاتها', (string) DB::table('failed_jobs')->count());
        }

        $held = WhatsAppMessage::whereNotNull('hold_until')
            ->where('status', WhatsAppMessage::STATUS_QUEUED)
            ->count();

        if ($held > 0) {
            $this->row('محجوزةٌ تنتظر وقتَها', '<fg=yellow>' . $held . '</>');
        }
    }

    private function verdict(): void
    {
        $this->newLine();

        if ($this->broken === null) {
            $this->line('<fg=green>السلسلةُ سليمةٌ من طرفها إلى طرفها.</>');
            $this->newLine();

            return;
        }

        $this->line('<fg=red>الحلقةُ المقطوعة:</> ' . $this->broken);

        if ($this->remedy !== null) {
            $this->line('<options=bold>ما يُصلحها:</> ' . $this->remedy);
        }

        $this->newLine();
    }

    // ── عرض ──────────────────────────────────────────────────────

    private function step(string $label, bool $ok, string $detail): void
    {
        $this->line('  ' . ($ok ? '<fg=green>✓</>' : '<fg=red>✗</>') . ' '
            . $this->pad($label, 34) . $detail);
    }

    private function row(string $label, string $value): void
    {
        $this->line('    ' . $this->pad($label, 24) . ': ' . $value);
    }

    /** أوّلُ عطلٍ يُسجَّل هو الحكم — وما بعده عرَضٌ له غالباً. */
    private function breakAt(string $what, ?string $remedy = null): void
    {
        if ($this->broken !== null) {
            return;
        }

        $this->broken = $what;
        $this->remedy = $remedy;
    }

    private function isOman(string $waId): bool
    {
        return str_starts_with($waId, WhatsAppContact::OMAN);
    }

    private function pad(string $text, int $width): string
    {
        $length = mb_strlen($text);

        return $length >= $width ? $text . ' ' : $text . str_repeat(' ', $width - $length);
    }
}
