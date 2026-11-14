<?php

namespace App\Observers;

use App\Models\FinanceInvoice;

/**
 * الفاتورةُ المرئيّة للموكّل تُخبره — بلا مبلغٍ في الرسالة.
 */
class FinanceInvoiceObserver
{
    public function created(FinanceInvoice $invoice): void
    {
        app(ClientNotifyObserver::class)->invoiceCreated($invoice);
    }

    /**
     * فاتورةٌ أُنشئت داخليّةً ثمّ عُلّمت مرئيّةً: هذه هي لحظةُ إتاحتها
     * للموكّل، لا لحظةُ إنشائها. ولولا هذا لما وصله إشعارٌ أبداً عن
     * فاتورةٍ يراها في بوابته.
     */
    public function updated(FinanceInvoice $invoice): void
    {
        if ($invoice->wasChanged('client_visible') && $invoice->client_visible) {
            app(ClientNotifyObserver::class)->invoiceCreated($invoice);
        }

        // «تسجيل دفعة» كان مفتاحاً في الإعدادات بلا طريقٍ يُطلقه.
        // وتصحيحُ مبلغِ فاتورةٍ مدفوعةٍ ليس دفعة: نموذجُ التعديل يعيد حسابَ
        // المدفوع = المبلغ، فكان رفعُ ١٠٠ إلى ١١٠ يُخبر الموكّلَ بدفعةٍ لم تكن
        $correctingPaid = $invoice->getOriginal('status') === 'paid'
            && $invoice->status === 'paid'
            && $invoice->wasChanged('amount');

        if (!$correctingPaid
            && $invoice->wasChanged('paid_amount')
            && (float) $invoice->paid_amount > (float) $invoice->getOriginal('paid_amount')) {
            app(ClientNotifyObserver::class)->paymentRecorded($invoice);
        }
    }
}
