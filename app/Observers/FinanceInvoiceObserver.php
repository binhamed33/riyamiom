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
    }
}
