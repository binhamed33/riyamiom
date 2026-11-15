<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;

/**
 * ═══ المطوّرُ لا يظهر نشاطُه في المكتب ═══
 *
 * «المطوّر ما يظهر نشاطه بالموقع بتاتاً» — طلبُ صاحب المنظومة.
 *
 * المطوّرُ يدخل مكاتبَ عملائه للدعم: يفتح مستنداً ليفحص عطلاً، ويعدّل
 * إعداداً. وكان كلُّ ذلك يظهر للمكتب كأنّه نشاطُ موظّف: «عرض مستند
 * ‹…› — عبدالرحمن» في «النشاط الأخير»، وفي سجلّ التدقيق، وفي مسار
 * القضية، وفي لوحة الحضور على ديسكورد.
 *
 * والقاعدة: يُسجَّل كلُّ شيءٍ كما هو (لا يُحذف أثرٌ ولا يُزوَّر)، ويُخفى
 * عند القراءة عن كلّ من ليس مطوّراً. والمطوّرُ يرى كلَّ شيء.
 *
 * وما فعله المطوّرُ ويبقى أثرُه في بيانات المكتب (قضيةٌ أنشأها، مستندٌ
 * رفعه) يبقى — هو بيانات المكتب لا نشاطُ شخص — لكنّ اسمَه لا يُذكر:
 * يُنسب إلى «نظام مُداوَلة» كما يُنسب ما تفعله الأتمتة.
 */
final class DeveloperShadow
{
    /** هل يُخفى المطوّرُ عن هذا القارئ؟ (القارئُ الحاليّ افتراضاً) */
    public static function applies(?User $viewer = null): bool
    {
        $viewer ??= auth()->user();

        return !($viewer instanceof User && $viewer->isDeveloper());
    }

    /**
     * يستثني من الاستعلام صفوفَ المطوّرين — وما لا فاعلَ له (النظام، الأتمتة،
     * الموكّل في البوابة) يبقى: NOT IN وحده كان يُسقط صفوفَ user_id = NULL.
     *
     * @template T of QueryBuilder
     * @param  T  $query
     * @return T
     */
    public static function hide($query, string $column = 'user_id', ?User $viewer = null)
    {
        if (!self::applies($viewer) || ($ids = self::ids()) === []) {
            return $query;
        }

        // قائمةُ معرّفاتٍ حرفيّة لا استعلامٌ فرعيّ: المطوّرون واحدٌ أو اثنان، وسجلُّ
        // التدقيق ملايينُ الصفوف — والاستعلامُ الفرعيّ كان يُقيَّم لكلّ صفّ
        return $query->where(fn ($q) => $q->whereNull($column)->orWhereNotIn($column, $ids));
    }

    /** معرّفاتُ المطوّرين — مرّةً للطلب الواحد. @return list<int> */
    public static function ids(): array
    {
        return once(fn () => User::query()->where('role', 'developer')->pluck('id')->map(fn ($id) => (int) $id)->all());
    }

    /** بريدُهم بحروفٍ صغيرة — مرّةً للطلب الواحد. @return list<string> */
    public static function emails(): array
    {
        return once(fn () => User::query()->where('role', 'developer')->pluck('email')
            ->filter()->map(fn ($e) => mb_strtolower(trim((string) $e)))->unique()->values()->all());
    }

    /** هل هذا المستخدمُ مطوّرٌ مخفيٌّ عن القارئ الحاليّ؟ */
    public static function hides(?User $user, ?User $viewer = null): bool
    {
        return $user instanceof User && $user->isDeveloper() && self::applies($viewer);
    }

    /** اسمُ الفاعل كما يُعرض: المطوّرُ يصير «نظام مُداوَلة» لغير المطوّرين. */
    public static function name(?User $user, ?string $fallback = null, ?User $viewer = null): ?string
    {
        if ($user === null) {
            return $fallback;
        }

        return self::hides($user, $viewer) ? self::label() : $user->name;
    }

    public static function label(): string
    {
        return __('app.developer_shadow_name');
    }
}
