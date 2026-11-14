<?php

namespace App\Observers;

use App\Models\Document;
use App\Models\FinanceInvoice;
use App\Models\LegalCase;
use App\Models\Session;
use App\Services\ClientPortal\ClientNotifications;
use App\Support\ClientEvents;
use Carbon\CarbonInterface;

/**
 * ما يستحقّ إخبارَ الموكّل به — يُلتقط عند مصدره.
 *
 * ═══ لماذا مراقبٌ لا نداءٌ في المتحكّم ═══
 *
 * القضيةُ تُنشأ من ثلاثة مواضع على الأقلّ: الشاشة، والاستيراد، ومركزُ
 * الأتمتة. ونداءٌ يُكتب في المتحكّم يُنسى في الاثنين الآخرين — فيصل
 * الإشعارُ من طريقٍ ولا يصل من طريقين، ولا أحد يعرف لماذا.
 *
 * والمراقبُ عند النموذج: يقع الحدثُ حيث تُكتب الحقيقة، لا حيث يُنقر
 * الزرّ.
 *
 * ═══ وما لا يُلتقط ═══
 *
 * ما عُلّم داخلياً لا يخرج. المستندُ غيرُ المرئي للموكّل، والفاتورةُ
 * غيرُ المرئيّة، والتحديثُ الداخلي — لا يُقيَّد لها إشعارٌ أصلاً، فلا
 * يمكن أن تُرسَل بخطأٍ لاحق.
 */
class ClientNotifyObserver
{
    public function caseCreated(LegalCase $case): void
    {
        $case->loadMissing('client');

        ClientNotifications::record(ClientEvents::CASE_CREATED, $case->client, $case, [
            'key' => (string) $case->id,
            'title' => 'فُتحت لكم قضيةٌ جديدة لدى المكتب.',
            'body' => self::caseLine($case),
            'target' => 'case',
            'target_id' => $case->id,
        ]);
    }

    public function caseUpdated(LegalCase $case): void
    {
        // الحالةُ وحدها: تعديلُ وصفٍ أو هاتفِ خصمٍ لا يعني الموكّلَ،
        // وإشعارٌ عن كلّ حفظةٍ يُفقد الإشعاراتِ كلَّها قيمتَها
        if (!$case->wasChanged('status')) {
            return;
        }

        $case->loadMissing('client');

        ClientNotifications::record(ClientEvents::CASE_STATUS, $case->client, $case, [
            // الحالةُ في المفتاح: انتقالان مختلفان حدثان مختلفان
            'key' => $case->id . ':' . $case->status,
            'title' => 'تغيّرت حالة قضيتكم.',
            'body' => self::caseLine($case),
            'target' => 'case',
            'target_id' => $case->id,
        ]);
    }

    public function sessionCreated(Session $session): void
    {
        $case = $session->case;
        $case?->loadMissing('client');

        if (!$case) {
            return;
        }

        // جلسةٌ تُقيَّد بعد انعقادها ومعها ما جرى فيها: رسالةٌ واحدة تكفي
        if ($this->sessionResult($session)) {
            return;
        }

        ClientNotifications::record(ClientEvents::SESSION_NEW, $case->client, $case, [
            'key' => (string) $session->id,
            'title' => 'حُدِّد موعدُ جلسةٍ في قضيتكم.',
            'body' => self::sessionLine($session, $case),
            'target' => 'sessions',
            'target_id' => $case->id,
        ]);
    }

    /**
     * المحامي كتب للموكّل ما جرى في الجلسة.
     *
     * المفتاحُ الجلسةُ وموعدُها: تصحيحُ كلمةٍ في النصّ لا يُرسل ثانيةً،
     * وجلسةٌ أُجّلت ثمّ كُتب ما جرى في موعدها الجديد خبرٌ جديد. والنصُّ
     * نفسُه لا يُرسل في واتساب — يُقرأ في البوابة بعد الدخول.
     *
     * ويُرجع «أُبلغ» ليغني عن خبر الموعد في الحفظة نفسها: سطرُه يحمل الموعد،
     * ورسالتان لفعلٍ واحد («حُدّد موعد» و«أضاف ما جرى») ضجيجٌ يُتجاهَل.
     */
    public function sessionResult(Session $session): bool
    {
        if (trim((string) $session->client_summary) === '') {
            return false;
        }

        $case = $session->case;
        $case?->loadMissing('client');

        if (!$case) {
            return false;
        }

        return null !== ClientNotifications::record(ClientEvents::SESSION_RESULT, $case->client, $case, [
            'key' => $session->id . ':' . optional($session->date)->format('YmdHi'),
            'title' => 'أضاف المكتبُ ما جرى في جلسة قضيتكم.',
            'body' => self::sessionLine($session, $case),
            'target' => 'sessions',
            'target_id' => $case->id,
        ]);
    }

    public function sessionUpdated(Session $session): void
    {
        // تأجيلٌ كُتب معه ما جرى: خبرُ «ما جرى» يحمل الموعدَ الجديد — رسالةٌ لا اثنتان
        $told = $session->wasChanged('client_summary') && $this->sessionResult($session);

        if ($told || !$session->wasChanged('date')) {
            return;
        }

        $case = $session->case;
        $case?->loadMissing('client');

        if (!$case) {
            return;
        }

        ClientNotifications::record(ClientEvents::SESSION_MOVED, $case->client, $case, [
            // الموعدُ الجديد في المفتاح: تأجيلان متتاليان حدثان
            'key' => $session->id . ':' . optional($session->date)->format('YmdHi'),
            'title' => 'تغيّر موعدُ جلسةٍ في قضيتكم.',
            'body' => self::sessionLine($session, $case),
            'target' => 'sessions',
            'target_id' => $case->id,
        ]);
    }

    public function documentCreated(Document $document): void
    {
        // غيرُ المرئي للموكّل لا يُقيَّد له إشعارٌ أصلاً
        if (!$document->client_visible) {
            return;
        }

        $case = $document->case;
        $case?->loadMissing('client');

        if (!$case) {
            return;
        }

        // ولا يُذكر اسمُ الملفّ: قد يحمل ما يكشف ما لا يُراد كشفُه في
        // إشعار شاشةٍ مقفلة — «حكم بالسجن.pdf» يُقرأ من بعيد
        ClientNotifications::record(ClientEvents::DOCUMENT_NEW, $case->client, $case, [
            'key' => (string) $document->id,
            'title' => 'أُتيح لكم مستندٌ جديد في ملفّ القضية.',
            'body' => self::caseLine($case),
            'target' => 'documents',
            'target_id' => $case->id,
        ]);
    }

    public function invoiceCreated(FinanceInvoice $invoice): void
    {
        if (!$invoice->client_visible) {
            return;
        }

        $client = $invoice->client ?? null;

        if (!$client) {
            return;
        }

        // ولا مبلغَ في الرسالة: المبالغُ في البوابة خلف الدخول
        ClientNotifications::record(ClientEvents::INVOICE_NEW, $client, $invoice->case, [
            'key' => (string) $invoice->id,
            'title' => 'صدرت فاتورةٌ جديدة باسمكم.',
            'body' => $invoice->invoice_number ? 'رقم الفاتورة: ' . $invoice->invoice_number : null,
            'target' => 'billing',
            'target_id' => $invoice->id,
        ]);
    }

    /** رسمٌ مرئيٌّ للموكّل أُضيف على قضيّته — ولا مبلغَ في الرسالة. */
    public function feeCreated(\App\Models\FinanceFee $fee): void
    {
        if (!$fee->client_visible) {
            return;
        }

        $case = $fee->case;
        $client = $case?->client;

        if (!$case || !$client) {
            return;
        }

        ClientNotifications::record(ClientEvents::FEE_NEW, $client, $case, [
            'key' => (string) $fee->id,
            'title' => 'أُضيف رسمٌ جديد على قضيتكم.',
            'body' => self::caseLine($case),
            'target' => 'billing',
            'target_id' => $case->id,
        ]);
    }

    /**
     * دفعةٌ قُيّدت على فاتورةٍ مرئيّةٍ للموكّل: زاد المدفوع.
     *
     * المفتاحُ رقمُ الفاتورة والمدفوعُ قبل الدفعة وبعدها — فدفعتان على الفاتورة
     * نفسِها إشعاران، وحفظُ الفاتورة بلا دفعةٍ جديدةٍ لا يكرّر شيئاً.
     */
    public function paymentRecorded(FinanceInvoice $invoice): void
    {
        if (!$invoice->client_visible || !$invoice->client) {
            return;
        }

        ClientNotifications::record(ClientEvents::PAYMENT_NEW, $invoice->client, $invoice->case, [
            // الانتقالُ لا المجموعُ وحده: ١٠٠ ← ٦٠ تصحيحاً ثمّ دفعةُ ٤٠ تعود إلى ١٠٠
            // — بمفتاح المجموع كانت تُطابق إشعارَ الدفعة الأولى فتُبتلع
            'key' => $invoice->id . ':' . number_format((float) $invoice->getOriginal('paid_amount'), 3, '.', '')
                . '>' . number_format((float) $invoice->paid_amount, 3, '.', ''),
            'title' => 'قُيّدت دفعةٌ على فاتورتكم.',
            'body' => $invoice->invoice_number ? 'رقم الفاتورة: ' . $invoice->invoice_number : null,
            'target' => 'billing',
            'target_id' => $invoice->id,
        ]);
    }

    // ── نصوصٌ قصيرة ─────────────────────────────────────────────

    private static function caseLine(LegalCase $case): ?string
    {
        $number = $case->case_number ?: $case->office_case_number;

        return $number ? 'رقم القضية: ' . $number : null;
    }

    private static function sessionLine(Session $session, LegalCase $case): ?string
    {
        $when = $session->date instanceof CarbonInterface
            ? $session->date->locale('ar')->isoFormat('dddd D MMMM YYYY') . ' الساعة ' . $session->date->format('H:i')
            : null;

        return implode(' · ', array_filter([self::caseLine($case), $when]));
    }
}
