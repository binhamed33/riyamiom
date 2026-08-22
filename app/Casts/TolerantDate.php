<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Carbon;

/**
 * تحويل عمود تاريخ لا ينهار على بيانات قديمة.
 *
 * لماذا لا نستعمل cast التاريخ العادي: قواعد قائمة منذ سنوات قد تحمل
 * '0000-00-00' أو نصّاً فارغاً في عمود تاريخ — وهو ما يحدث حين تُنشأ
 * القاعدة بلا وضع صارم. cast التاريخ العادي يرمي استثناءً على مثل هذه
 * القيمة، فتسقط كل صفحة تعرض القضية بدل أن تعرضها بتاريخ فارغ.
 *
 * القراءة متسامحة: ما لا يُقرأ تاريخاً يعود null.
 * الكتابة محافظة: ما لا يُقرأ يُمرَّر كما هو، فلا يتغيّر سلوك الحفظ
 * عمّا كان قبل هذا الصنف.
 */
class TolerantDate implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?Carbon
    {
        if ($value === null || $value === '' || str_starts_with((string) $value, '0000')) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    public function set($model, string $key, $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        if ($value instanceof \DateTimeInterface) {
            return [$key => Carbon::instance($value)->toDateString()];
        }

        try {
            return [$key => Carbon::parse($value)->toDateString()];
        } catch (\Throwable) {
            // لا نخترع قيمة: نمرّرها كما وصلت، فيبقى الحفظ كما كان
            return [$key => $value];
        }
    }
}
