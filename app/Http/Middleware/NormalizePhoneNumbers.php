<?php

namespace App\Http\Middleware;

use App\Support\Phone;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * صيغةٌ واحدةٌ للرقم في القاعدة: E.164.
 *
 * ═══ المشكلة ═══
 *
 * الموظّفُ يكتب ‎91234567‎، والموكّلُ يصل من واتساب بـ‎96891234567‎،
 * والسعوديُّ يكتب ‎0552000531‎ بصفره المحلّيّ. وثلاثتُها أرقامٌ صحيحة
 * تُحفظ ثلاثَ صيغٍ مختلفة، فيبحث المحامي عن موكّله برقمه فلا يجده،
 * ويُرسل واتساب إلى رقمٍ بلا مفتاحٍ فلا يصل.
 *
 * ═══ العلاج ═══
 *
 * المنتقي يبعث الرقمَ المحلّيَّ في ‎phone‎ ودولتَه في ‎phone_country‎.
 * وهنا يُجمعان في ‎+96891234567‎ قبل أن يبلغا المتحكّم — فلا متحكّمَ
 * يتذكّر، ولا حقلَ يُنسى حين يُضاف نموذجٌ جديد.
 *
 * ═══ وثلاثةُ حرّاسٍ كي لا يمسّ ما ليس له ═══
 *
 * ١) لا بدّ من حقلٍ مرافقٍ اسمه ‎{الحقل}_country‎ — والنظامُ ليس فيه
 *    حقلٌ بهذا الاسم لغير الهاتف.
 * ٢) وقيمتُه رمزُ دولةٍ تعرفها المكتبة، لا أيَّ حرفين.
 * ٣) ولا يُكتب شيءٌ إلا إذا خرج رقمٌ **صحيح**. فالخطأُ يمرّ كما كتبه
 *    صاحبُه ليراه في رسالة الرفض — لا مقصوصاً ولا مبدَّلاً.
 */
class NormalizePhoneNumbers
{
    /** لاحقةُ الحقل المرافق الذي يحمل الدولة. */
    public const COUNTRY_SUFFIX = '_country';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            return $next($request);
        }

        $merge = [];

        foreach ($request->all() as $key => $region) {
            if (!is_string($key) || !str_ends_with($key, self::COUNTRY_SUFFIX)) {
                continue;
            }

            $field = substr($key, 0, -strlen(self::COUNTRY_SUFFIX));

            if ($field === '' || !is_string($region)
                || !preg_match('/^[A-Za-z]{2}$/', $region)
                || Phone::dialCode($region) === null) {
                continue;
            }

            $value = $request->input($field);

            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            if (Phone::isValid($value, $region)) {
                $merge[$field] = Phone::e164($value, $region);
            }
        }

        if ($merge !== []) {
            $request->merge($merge);
        }

        return $next($request);
    }
}
