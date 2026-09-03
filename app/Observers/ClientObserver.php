<?php

namespace App\Observers;

use App\Models\Client;
use App\Services\ClientPortal\PortalLinks;

/**
 * روابطُ البوابة تُبطَل حين يتغيّر صاحبُها.
 *
 * ═══ الثغرة ═══
 *
 * PortalLinks::revokeAllFor() كُتبت لهذا بالضبط — «يُنادى عند تغيّر
 * هاتفه أو حذفه» يقول تعليقُها — ولم يكن لها منادٍ واحدٌ في التطبيق
 * كلِّه. فالحدُّ الثالث من حدودها الثلاثة («إبطالٌ صريح») لم يكن
 * موجوداً وقتَ التشغيل.
 *
 * ═══ ما يعنيه ذلك ═══
 *
 * شركةُ الاتصالات تعيد بيع الرقم بعد انقطاعه، أو يُصحَّح رقمُ الموكّل
 * في السجلّ. والروابطُ التي أُرسلت إلى الرقم القديم تبقى حيّةً بقيّةَ
 * مدّتها — أسبوعاً افتراضاً، وشهراً إن مُدّت. فمن صار يملك ذلك الخطَّ
 * يفتح الرابطَ ويدخل بوابةَ الموكّل: قضاياه وجلساتُه وفواتيرُه.
 *
 * والحذفُ مثلُه: موكّلٌ حُذف تبقى روابطُه تفتح بوابتَه.
 */
class ClientObserver
{
    public function updated(Client $client): void
    {
        // الهاتفُ والهويّةُ هما ما يُثبت به صاحبُ الرابط نفسَه —
        // فتغيّرُ أحدِهما يعني أنّ من كان يملك الإثباتَ لم يعد يملكه
        if ($client->wasChanged(['phone', 'national_id'])) {
            PortalLinks::revokeAllFor($client);
        }
    }

    public function deleting(Client $client): void
    {
        PortalLinks::revokeAllFor($client);
    }
}
