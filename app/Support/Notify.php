<?php

namespace App\Support;

use App\Models\Notification;

/**
 * إنشاء إشعار يُترجَم عند العرض.
 *
 * كان كل موضع يكتب نصّ الإشعار حرفياً — بعضه بالعربية وبعضه
 * بالإنجليزية — فيصل الموظّف إشعارٌ بلغة لم يخترها، ولا سبيل لتغييره
 * بعد حفظه. هنا يُحفظ المفتاح ومعاملاته، والنصّ يُبنى وقت القراءة
 * بلغة قارئه.
 *
 * ويُحفظ النصّ الحرفي أيضاً في العمودين القديمين: لا شيء يعتمد عليهما
 * ينكسر، ومن يقرأ القاعدة مباشرة يفهم ما فيها.
 */
class Notify
{
    /**
     * @param  array<string, mixed>  $params
     */
    public static function send(
        int $userId,
        string $titleKey,
        string $messageKey,
        array $params = [],
        string $type = Notification::TYPE_INFO,
        ?string $notifiableType = null,
        ?int $notifiableId = null,
    ): ?Notification {
        try {
            return Notification::create([
                'user_id' => $userId,
                // النصّ الافتراضي بلغة النظام — لمن يقرأ القاعدة مباشرة
                'title' => __($titleKey, $params, config('app.locale')),
                'message' => __($messageKey, $params, config('app.locale')),
                'title_key' => $titleKey,
                'message_key' => $messageKey,
                'params' => $params,
                'type' => $type,
                'is_read' => false,
                'notifiable_type' => $notifiableType,
                'notifiable_id' => $notifiableId,
            ]);
        } catch (\Throwable $e) {
            // إشعار لم يُحفظ لا يُفشل العملية التي أنشأته
            report($e);

            return null;
        }
    }
}
