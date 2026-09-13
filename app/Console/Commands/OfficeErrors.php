<?php

namespace App\Console\Commands;

use App\Support\ErrorPulse;
use Illuminate\Console\Command;

/**
 * ما الأخطاء في هذا المكتب — مجموعةً لا مبعثرة.
 *
 * ═══ لماذا أمرٌ لا فتحُ السجلّ بالعين ═══
 *
 * ‏laravel.log يبلغ عشرات الميغابايت، فيه الخطأُ الواحد مكرَّراً ألفَ
 * مرّة. ومن يفتحه يرى آخرَ عشرين سطراً — وقد تكون كلُّها نسخاً من عطبٍ
 * واحد، فيظنّ أنّ فيه عطباً واحداً وهي عشرة.
 *
 * وهذا يجمعها: النوعُ × المسارُ × الملفُّ والسطر، وكم مرّة وقع كلٌّ.
 * فخمسةَ عشرَ سطراً تقول ما تقوله عشرةُ آلاف.
 *
 * ═══ ولا يُطبَع نصُّ الخطأ ═══
 *
 * رسالةُ خطأ قاعدة البيانات تحمل ما في الصفّ: «Duplicate entry
 * 'أحمد الريامي' for key 'clients_phone'». وهذا المخرَجُ يُنسخ ويُرسَل
 * لمن يُصلح، فلا يخرج منه اسمُ موكّل. والنوعُ والموضعُ يكفيان للإصلاح،
 * والتفصيلُ يبقى في الخادم لمن يملك الدخول إليه.
 */
class OfficeErrors extends Command
{
    protected $signature = 'office:errors
        {--hours=24 : نافذةُ القراءة بالساعات}
        {--limit=15 : كم مجموعةً تُعرض}';

    protected $description = 'أخطاءُ المكتب مجموعةً بنوعها وموضعها — بلا نصوصها';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $rows = ErrorPulse::breakdown(now()->subHours($hours), (int) $this->option('limit'));

        if ($rows === []) {
            $this->info("لا أخطاء في آخر {$hours} ساعة ✓");

            return self::SUCCESS;
        }

        $total = array_sum(array_column($rows, 'count'));

        $this->line('');
        $this->line("  أخطاءُ آخر {$hours} ساعة — " . $total . ' وقعة في ' . count($rows) . ' مجموعة');
        $this->line('');

        // ═══ عمودُ «التفصيل» ═══
        //
        // «QueryException» وحدها لا تقول شيئاً: عمودٌ مفقود؟ جدولٌ غائب؟
        // تكرارُ مفتاح؟ والصنفُ (Column not found 1054) ثابتٌ في المحرّك
        // لا بيانٌ من الصفوف — فيُطبَع، ويبقى نصُّ الخطأ حيث هو.
        $this->table(
            ['مرّة', 'النوع', 'التفصيل', 'المسار', 'الموضع', 'آخرها'],
            array_map(fn ($r) => [
                $r['count'],
                $r['type'],
                $r['detail'] ?? '—',
                $r['route'] ?? '—',
                $r['origin'] ?? '—',
                $r['last_at'],
            ], $rows),
        );

        $this->line('  نصوصُ الأخطاء لا تُطبع هنا — فيها بيانات الموكّلين. وهي في:');
        $this->line('  ' . storage_path('logs/laravel.log'));
        $this->line('');

        // رمزُ الخروج يقول «فيه أخطاء» لمن يبني عليه شرطاً في سكربت
        return self::FAILURE;
    }
}
