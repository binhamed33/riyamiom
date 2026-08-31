<?php

namespace App\Observers;

use App\Models\CaseActivity;
use App\Models\LegalCase;
use App\Support\CaseTimeline;

/** تحدُّث حالة القضية خبرٌ يخصّ الموكّل ويسأل عنه. */
class LegalCaseObserver
{
    /**
     * قضيّةٌ فُتحت — يُخبَر بها الموكّل إن شغّل المكتبُ ذلك.
     *
     * والمراقبُ لا المتحكّم: القضيةُ تُنشأ من الشاشة ومن الاستيراد
     * ومن مركز الأتمتة، ونداءٌ في متحكّمٍ واحد يترك الطريقين الآخرين
     * صامتين — فيصل الإشعارُ أحياناً ولا يصل أحياناً بلا سببٍ ظاهر.
     */
    public function created(LegalCase $case): void
    {
        app(\App\Observers\ClientNotifyObserver::class)->caseCreated($case);
    }

    public function updated(LegalCase $case): void
    {
        app(\App\Observers\ClientNotifyObserver::class)->caseUpdated($case);

        if (!$case->wasChanged('status')) {
            return;
        }

        // الضبط الأول ليس تحدُّثاً.
        //
        // القالب يضبط الحالة لحظة تسجيل القضية، فيرى الموكّل «— ← قيد
        // النظر» في اللحظة التي سُجّلت فيها قضيته. سطرٌ لا يخبره بشيء
        // ويسبق سطر «تم تسجيل القضية» نفسه.
        $before = $case->getOriginal('status');

        if ($before === null || $before === '') {
            return;
        }

        CaseTimeline::log(
            $case->id,
            CaseActivity::TYPE_STATUS,
            'تحدّثت حالة القضية',
            $this->label($before) . ' ← ' . $this->label($case->status)
        );
    }

    private function label(?string $status): string
    {
        $key = 'app.status_' . $status;

        return __($key) === $key ? (string) $status : __($key);
    }
}
