<?php

namespace App\Console\Commands;

use App\Mail\MailKind;
use App\Mail\SystemNoticeMail;
use App\Services\OfficeMailer;
use App\Support\MailIdentity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;

/**
 * فحصُ البريد وإرسالُ رسالة تجربة.
 *
 * ═══ ما لا يُطبع هنا ═══
 *
 * كلمةُ المرور لا تُطبع ولا يُطبع طولُها ولا أوّلُ حرفٍ منها. واسمُ
 * المستخدم يُطبع محجوباً. لأنّ من يشغّل هذا الأمر يلصق مخرجاتِه في
 * محادثةِ دعمٍ أو تذكرة، وما طُبع مرةً خرج من يده.
 */
class MailDoctor extends Command
{
    protected $signature = 'mail:doctor
                            {--to= : عنوانٌ تُرسَل إليه رسالة تجربة}
                            {--now : أرسل مباشرةً بلا طابور — لرؤية خطأ SMTP فوراً}
                            {--probe : تحقّق من الاعتماد لدى الخادم بلا إرسال رسالة}';

    protected $description = 'يفحص إعداد البريد ويرسل رسالة تجربة';

    public function handle(): int
    {
        $this->line('');
        $this->components->info('إعداد البريد');

        foreach (MailIdentity::diagnostics() as $key => $value) {
            $this->line(sprintf('  %-16s %s', $key, $value));
        }

        // ثغراتُ الهويّة تُقال هنا لا في «الجاهزية»: هذه يسدّها المكتب
        // من شاشة إعداداته، وتلك يصلحها من يملك الخادم.
        foreach (MailIdentity::identityIssues() as $issue) {
            $this->line('');
            $this->components->warn($issue['text']);
        }

        $this->line('');
        $this->components->info('أنواع الإشعارات');

        foreach (MailKind::all() as $kind) {
            $this->line(sprintf(
                '  [%s] %-32s (%s)',
                $kind->isEnabled() ? '✓' : ' ',
                $kind->label(),
                $kind->settingKey(),
            ));
        }

        $this->line('');
        $ok = $this->report();

        if ($this->option('probe')) {
            return $this->probe() ? self::SUCCESS : self::FAILURE;
        }

        $to = $this->option('to');

        if (!is_string($to) || $to === '') {
            $this->line('');
            $this->line('  لإرسال رسالة تجربة:  php artisan mail:doctor --to=you@example.com');
            $this->line('  للتحقق من الاعتماد بلا إرسال:  php artisan mail:doctor --probe');

            return $ok ? self::SUCCESS : self::FAILURE;
        }

        return $this->sendTest($to) ? self::SUCCESS : self::FAILURE;
    }

    /** حالةُ الأركان الأربعة التي بدونها لا يصل بريد. */
    private function report(): bool
    {
        $this->components->info('الجاهزية');
        $ok = true;

        $configured = MailIdentity::isConfigured();
        $this->state($configured, 'SMTP مضبوط', 'السائق log أو array — الرسائل تُكتب ولا تُرسَل');
        $ok = $ok && $configured;

        $from = MailIdentity::fromAddress();
        // hello@example.com هو افتراض لارافل نفسه: وجودُه يعني أنّ أحداً
        // لم يضبط MAIL_FROM_ADDRESS، لا أنّه ضُبط على هذه القيمة.
        $hasFrom = MailIdentity::isDeliverable($from)
            && !in_array($from, ['no-reply@localhost', 'hello@example.com'], true);
        $this->state($hasFrom, 'عنوان المُرسِل مضبوط: ' . $from, 'MAIL_FROM_ADDRESS ناقص في .env (ما زال على القيمة الافتراضية)');
        $ok = $ok && $hasFrom;

        $queue = (string) config('queue.default', 'sync');
        $this->state(
            $queue !== 'sync',
            'الطابور: ' . $queue,
            'الطابور sync — البريد يُرسَل داخل الطلب: «حفظ القضية» ينتظر Gmail. اضبط QUEUE_CONNECTION=database',
        );

        $pending = $this->pending();
        $this->line(sprintf('  •  في طابور البريد الآن: %s رسالة', $pending['pending']));
        $this->line(sprintf('  •  أخفقت نهائياً: %s', $pending['failed']));

        if (is_int($pending['pending']) && $pending['pending'] > 20) {
            $this->components->warn('الطابور متراكم — تأكّد أنّ cron يشغّل schedule:run كل دقيقة.');
        }

        return $ok;
    }

    /** @return array{pending:int|string, failed:int|string} */
    private function pending(): array
    {
        if ((string) config('queue.default') !== 'database') {
            return ['pending' => '—', 'failed' => '—'];
        }

        try {
            return [
                'pending' => DB::table('jobs')->where('queue', 'mail')->count(),
                'failed' => DB::table('failed_jobs')->count(),
            ];
        } catch (\Throwable) {
            return ['pending' => '—', 'failed' => '—'];
        }
    }

    private function sendTest(string $to): bool
    {
        if (!MailIdentity::isDeliverable($to)) {
            $this->components->error('العنوان المُدخَل غير صالح.');

            return false;
        }

        $this->line('');

        // لا يُقال «أُرسلت» ما لم يكن هناك ناقلٌ يُرسل. السائق log يكتب
        // الرسالة في السجلّ، وarray يبتلعها — وكلاهما ينجح فيظنّ المشرف
        // أنّ البريد يعمل، ويكتشف الحقيقة من شكوى موكّل.
        if (!MailIdentity::isConfigured()) {
            $this->components->error(
                'لم تُرسَل: السائق «' . config('mail.default') . '» لا يُرسل شيئاً — يكتب في السجلّ أو يبتلع.'
            );
            $this->line('');
            $this->line('  اضبط SMTP أولاً:  bash scripts/set-mail-credentials.sh');

            return false;
        }

        $this->components->info('إرسال رسالة تجربة إلى ' . MailIdentity::maskEmail($to));

        $mail = new SystemNoticeMail(
            heading: 'رسالة تجربة',
            bodyText: "وصلت هذه الرسالة، أي أنّ إعداد البريد في هذا الخادم سليم.\n\n"
                . "أُرسلت من نظام **مُداوَلة** بتاريخ " . now()->format('Y-m-d H:i') . ".\n\n"
                . 'لا حاجة إلى الردّ عليها.',
        );

        try {
            if ($this->option('now')) {
                // بلا طابور: خطأ SMTP يظهر هنا في وجهك لا في السجلّ بعد حين
                Mail::to($to)->send($mail);
                $this->components->info('أُرسلت مباشرةً. تحقّق من صندوق الوارد (وصندوق الرسائل غير المرغوبة).');

                return true;
            }
        } catch (\Throwable $e) {
            $this->components->error(MailIdentity::scrub($e->getMessage()));
            $this->hint($e);

            return false;
        }

        $result = OfficeMailer::send($to, $mail);

        if ($result['status'] !== OfficeMailer::SENT) {
            $this->components->error($result['reason'] ?? 'تعذّر الإرسال.');

            return false;
        }

        $this->components->info('أُدرجت في الطابور. تخرج خلال دقيقة إن كان cron يعمل.');
        $this->line('  لإرسالها الآن يدوياً:  php artisan queue:work --queue=mail --stop-when-empty');

        return true;
    }

    /**
     * مصافحةٌ كاملة بلا رسالة: اتصال، ثم EHLO، ثم TLS، ثم AUTH.
     *
     * ═══ لماذا يلزم فحصٌ لا يُرسل ═══
     *
     * كلمةُ المرور تُضبط على عشرة مكاتب دفعةً واحدة، وواحدٌ منها قد
     * يخرج بقيمةٍ مقطوعة أو قديمة. والطريقة الوحيدة قبل اليوم لاكتشاف
     * ذلك كانت إرسال عشر رسائل حقيقية — أو انتظار موكّلٍ لا تصله
     * رسالته. وهذا يسأل الخادم: هل تقبل هذا الاعتماد؟ ثم ينصرف.
     *
     * ولا تُطبع كلمةُ المرور ولا جزءٌ منها في أي مسار: ردُّ الخادم
     * نفسه يمرّ بـ scrub لأنّ بعض الخوادم تُعيد ما أُرسل إليها.
     */
    private function probe(): bool
    {
        $this->line('');

        if (!MailIdentity::isConfigured()) {
            $this->components->error(
                'لا شيء يُفحص: السائق «' . config('mail.default') . '» لا يتصل بخادم بريد أصلاً.'
            );

            return false;
        }

        $transport = Mail::mailer()->getSymfonyTransport();

        if (!$transport instanceof SmtpTransport) {
            $this->components->warn('الناقل ليس SMTP — لا مصافحة تُفحص.');

            return true;
        }

        $this->components->info('مصافحة ' . config('mail.mailers.smtp.host') . ' بلا إرسال…');

        try {
            $transport->start();
        } catch (\Throwable $e) {
            $this->components->error(MailIdentity::scrub($e->getMessage()));
            $this->hint($e);

            return false;
        } finally {
            // الاتصال يُغلق في الحالين: جلسةٌ معلّقة تبقى محسوبةً على
            // الحساب عند Gmail، وحدُّه على الجلسات المتزامنة ضيّق.
            try {
                $transport->stop();
            } catch (\Throwable) {
                // إغلاقٌ متعذّر بعد إخفاق الاتصال لا يضيف شيئاً
            }
        }

        $this->components->info('الخادم قَبِل الاعتماد. البريد جاهز للإرسال من هذا المكتب.');

        return true;
    }

    /** ترجمةُ أشهر أخطاء Gmail إلى الخطوة المطلوبة. */
    private function hint(\Throwable $e): void
    {
        $message = $e->getMessage();

        $hint = match (true) {
            str_contains($message, '535') || str_contains($message, 'Username and Password not accepted')
                => 'Gmail رفض الاعتماد. استخدم App Password من حساب Google (لا كلمة مرور الحساب)، وفعّل التحقق بخطوتين أولاً.',
            str_contains($message, 'Connection could not be established') || str_contains($message, 'Connection timed out')
                => 'لا يصل الخادم إلى smtp.gmail.com:587 — الأرجح أنّ جدار الحماية يمنع المنفذ الصادر.',
            str_contains($message, 'certificate') || str_contains($message, 'SSL')
                => 'مشكلة شهادة TLS. تأكّد أنّ المنفذ 587 مع MAIL_SCHEME فارغ (STARTTLS) أو 465 مع smtps.',
            default => null,
        };

        if ($hint !== null) {
            $this->line('');
            $this->components->warn($hint);
        }
    }

    private function state(bool $ok, string $good, string $bad): void
    {
        $this->line('  ' . ($ok ? '✓' : '✗') . '  ' . ($ok ? $good : $bad));
    }
}
