<?php

namespace App\Observers;

use App\Models\FinanceFee;

/**
 * الرسمُ يُشعَر به الموكّل كالفاتورة.
 *
 * كان «إضافة رسم» من صفحة القضية يكتب الرسمَ ويظهر في البوابة، ولا يُطلق حدثاً
 * أصلاً — فلا إشعارَ ولا رسالةَ واتساب، والمكتبُ يظنّ أنّ واتساب معطّل.
 */
class FinanceFeeObserver
{
    public function created(FinanceFee $fee): void
    {
        app(ClientNotifyObserver::class)->feeCreated($fee);
    }

    /** رسمٌ أُنشئ داخليّاً ثمّ عُلّم مرئيّاً: هذه لحظةُ إتاحته للموكّل. */
    public function updated(FinanceFee $fee): void
    {
        if ($fee->wasChanged('client_visible') && $fee->client_visible) {
            app(ClientNotifyObserver::class)->feeCreated($fee);
        }
    }
}
