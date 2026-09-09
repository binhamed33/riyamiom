<?php

namespace Tests\Feature;

use App\Support\Duration;
use Tests\TestCase;

/**
 * المدّةُ تُقرأ كما كُتبت.
 *
 * ═══ ما وقع على شاشة المالك ═══
 *
 * عمودُ «المدة» في سجلّ الحضور كان يعرض:
 *
 *     س 15د 6        ⇽ والمقصود «6 س 15 د»
 *     س 44د 5        ⇽ والمقصود «5 س 44 د»
 *     س 0د 0         ⇽ لموظّفةٍ سجّلت حضورَها وانصرافَها في الدقيقة نفسِها
 *
 * الرقمُ يقفز إلى آخر السطر. والحسابُ سليمٌ تماماً: العطبُ في العرض.
 * الخليّةُ كانت ‎dir="ltr"‎ ونصُّها يخلط أرقاماً لاتينيّةً بحرفين
 * عربيّين، فتقسمه خوارزميّةُ الاتّجاهين في المتصفّح إلى مقاطعَ وتعيد
 * ترتيبها — فيخرج ما لم يُكتب.
 *
 * ═══ ولماذا لا يمسكه اختبارُ محتوى ═══
 *
 * النصُّ في مصدر الصفحة صحيحٌ حرفاً بحرف: «6س 15د». والقلبُ يقع في
 * المتصفّح عند الرسم لا في الخادم. فلا اختبارَ يقرأ HTML يراه — ولا
 * يُرى إلا بالعين على شاشة.
 *
 * فما يُحرَس هنا شرطان يمنعان عودتَه:
 *   ١) أن يبقى اتّجاهُ الخليّة rtl — وهو ما يجعل الترتيبَ صحيحاً.
 *   ٢) وألّا يُبنى نصُّ المدّة بيده في قالبٍ من جديد.
 */
class AttendanceDurationTest extends TestCase
{
    /** @return array<string, array{0: ?int, 1: string}> */
    public static function durations(): array
    {
        return [
            'لا تسجيل' => [null, '—'],
            'دقيقةٌ واحدةٌ لم تكتمل' => [0, 'أقلّ من دقيقة'],
            'دقائقُ وحدها' => [45, '45 د'],
            'ساعةٌ تامّة' => [60, '1 س'],
            'ساعتان تامّتان' => [120, '2 س'],
            'ساعاتٌ ودقائق' => [375, '6 س 15 د'],
            'الصفُّ الذي في الشاشة' => [344, '5 س 44 د'],
            'يومُ عملٍ طويل' => [671, '11 س 11 د'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('durations')]
    public function test_a_duration_reads_as_it_is_written(?int $minutes, string $expected): void
    {
        $this->assertSame($expected, Duration::human($minutes));
    }

    /**
     * ‏«0 س 0 د» رقمان صحيحان لا يعنيان شيئاً لقارئ.
     *
     * والموظّفةُ التي سجّلت حضورَها وانصرافَها في الدقيقة نفسِها أوضحُ
     * عنها أن يُقال «أقلّ من دقيقة» — وهي الحالةُ التي في الصفّ الأخير
     * من الشاشة.
     */
    public function test_zero_is_said_in_words_not_in_two_zeros(): void
    {
        $this->assertSame('أقلّ من دقيقة', Duration::human(0));
        $this->assertStringNotContainsString('0 س', Duration::human(0));
    }

    /** ومدّةٌ سالبةٌ — ساعةُ خادمٍ قفزت — لا تُعرض رقماً بالسالب. */
    public function test_a_negative_duration_is_not_shown_as_a_number(): void
    {
        $this->assertSame('—', Duration::human(-30));
    }

    /**
     * ═══ الشرطُ الذي يمنع عودةَ العطب ═══
     *
     * كلُّ خليّةِ مدّةٍ تحمل ‎dir="rtl"‎. ولو عادت إحداها إلى ltr عاد
     * القلبُ معها — ولا يظهر في أيّ اختبارٍ آخر ولا في أيّ سجلّ.
     */
    public function test_every_duration_cell_is_right_to_left(): void
    {
        $view = file_get_contents(resource_path('views/hr/index.blade.php'));

        preg_match_all('/<td[^>]*Duration::human[^<]*/u', $view, $cells);
        preg_match_all('/<td([^>]*)>\{\{[^}]*Duration::human/u', $view, $attrs);

        $this->assertNotEmpty($attrs[1], 'لا خليّةَ مدّةٍ في القالب أصلاً');

        foreach ($attrs[1] as $attr) {
            $this->assertStringContainsString('dir="rtl"', $attr,
                'خليّةُ مدّةٍ بلا rtl — الرقمُ سيقفز إلى آخر السطر: ' . trim($attr));
            $this->assertStringContainsString('text-center', $attr,
                'خليّةُ مدّةٍ غيرُ متوسّطة: ' . trim($attr));
        }
    }

    /**
     * ولا يُبنى نصُّ المدّة بيده من جديد.
     *
     * كان مبنيّاً في ثلاثة مواضعَ من القالب نفسِه، متطابقةً حرفاً بحرف.
     * فمن أصلح واحدةً ترك اثنتين — وهذا ما يجعل العطبَ يعود بعد إصلاحه.
     */
    public function test_no_view_builds_the_duration_text_by_hand(): void
    {
        $offenders = [];

        foreach (glob(resource_path('views/**/*.blade.php')) ?: [] as $file) {
            $body = (string) file_get_contents($file);

            if (preg_match("/intdiv\([^)]*(minutes|duration)[^)]*,\s*60\)/i", $body)) {
                $offenders[] = str_replace(resource_path('views/'), '', $file);
            }
        }

        $this->assertSame([], $offenders,
            'قالبٌ يبني نصَّ المدّة بيده بدل Duration::human — سيفترق عن البقيّة: '
            . implode(', ', $offenders));
    }
}
