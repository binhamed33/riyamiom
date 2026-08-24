<?php

namespace App\Mail;

use App\Support\MailIdentity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * كل بريدٍ يخرج من المكتب يمرّ من هنا.
 *
 * ═══ لماذا مؤجَّل ═══
 *
 * مصافحةُ Gmail تستغرق ثانيةً إلى ثلاث. وإرسالُها داخل الطلب يجعل
 * «حفظ القضية» ينتظرها، وإخفاقُ الشبكة يجعله ينتظر حتى المهلة ثم يفشل
 * — فيُحرم المحامي من قيد قضيته لأنّ بريداً لم يخرج. فتُلقى الرسالة في
 * الطابور ويعود الطلب فوراً، ويحملها العاملُ بعد ذلك.
 *
 * وطابورٌ باسمه («mail») لا الطابور العام: عاملُ البريد يُصرَّف من
 * المجدول كلَّ دقيقة، ولا يُقحَم على مهامٍ أخرى في الطابور العام لها
 * حسابُها.
 *
 * ═══ وماذا لو أخفق ═══
 *
 * ثلاث محاولات بتباعدٍ متزايد، ثم يُدوَّن الإخفاق ويُنسى. ولا يُدوَّن
 * إلا بعد تنقيةٍ من كلمة المرور واسم المستخدم: ردُّ خادم SMTP يُنقل
 * كما هو، وسجلُّ المكتب يُقرأ ويُنسخ ويُرسَل عند الشكوى.
 */
abstract class OfficeMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /** ثلاث محاولات: عطلُ شبكةٍ لحظيّ يمرّ، وعطلٌ حقيقيّ لا يُعاد إلى الأبد. */
    public int $tries = 3;

    /**
     * دقيقة، ثم خمس، ثم ربع ساعة — فتنتهي المحاولات خلال ربع ساعة.
     *
     * ولا تُضبط retryUntil معها: لارافل يُلغي $tries متى وُجدت مهلةٌ
     * زمنية (Worker::shouldFailOnTimeout)، فتصير المحاولات بلا عدد.
     */
    public array $backoff = [60, 300, 900];


    public function __construct(public readonly MailKind $kind)
    {
        $this->onQueue('mail');
    }

    /**
     * هل ما زال للرسالة موضوع لحظةَ خروجها؟
     *
     * بين الدفع والإرسال دقيقة، وقد تُحذف القضية فيها. وقولُ «قُيّدت
     * قضيتكم» عن قضيةٍ لم تعد موجودة خطأٌ يصل الموكّل ولا يُستدرك.
     *
     * ولا تُترك الحراسةُ لـ SerializesModels: لارافل يقرأ
     * deleteWhenMissingModels من صنف المهمّة — SendQueuedMailable —
     * لا من الرسالة، فوضعُها هنا لا يفعل شيئاً. والحمولةُ نفسها صارت
     * قيماً مبنيّةً وقتَ الدفع لا نموذجاً يُعاد جلبه، فلا ModelNotFound
     * أصلاً؛ وهذه البوّابة تفحص الوجود صراحةً.
     */
    protected function stillRelevant(): bool
    {
        return true;
    }

    public function send($mailer)
    {
        if (!$this->stillRelevant()) {
            return null;
        }

        return parent::send($mailer);
    }

    abstract protected function subjectLine(): string;

    /** @return array<string, mixed> */
    abstract protected function data(): array;

    public function envelope(): Envelope
    {
        $replyTo = MailIdentity::replyTo();

        return new Envelope(
            from: new Address(
                MailIdentity::fromAddress(),
                MailIdentity::fromName(system: !$this->kind->isFromOffice()),
            ),
            replyTo: $replyTo !== null ? [new Address($replyTo)] : [],
            subject: $this->subjectLine(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: $this->kind->view(),
            with: array_merge($this->data(), [
                'officeName' => MailIdentity::fromName(),
                'replyTo' => MailIdentity::replyTo(),
                'subject' => $this->subjectLine(),
                'kind' => $this->kind,
            ]),
        );
    }

    /**
     * أخفقت المحاولات كلُّها.
     *
     * يُدوَّن النوع والسبب — لا عنوان الموكّل ولا نصّ الرسالة: بيانات
     * الموكّلين لا تُنثر في السجلّ.
     */
    public function failed(\Throwable $e): void
    {
        Log::error('Mail delivery failed [' . $this->kind->value . ']: ' . MailIdentity::scrub($e->getMessage()));
    }
}
