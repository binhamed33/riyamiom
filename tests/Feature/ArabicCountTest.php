<?php

namespace Tests\Feature;

use App\Support\ArabicCount;
use Tests\TestCase;

/**
 * العدد في العربية لا يُلحق بالمعدود كما في الإنجليزية.
 * «منذ 6 يوماً» خطأ يقرأه المحامي فيظنّ النظام أعجميّاً.
 */
class ArabicCountTest extends TestCase
{
    public function test_one_and_two_are_not_counted_with_a_number()
    {
        $this->assertSame('يوم واحد', ArabicCount::days(1));
        $this->assertSame('يومان', ArabicCount::days(2));
    }

    public function test_three_to_ten_take_the_plural_of_paucity()
    {
        $this->assertSame('3 أيام', ArabicCount::days(3));
        $this->assertSame('6 أيام', ArabicCount::days(6));
        $this->assertSame('10 أيام', ArabicCount::days(10));
    }

    public function test_eleven_and_above_take_the_singular()
    {
        $this->assertSame('11 يوماً', ArabicCount::days(11));
        $this->assertSame('45 يوماً', ArabicCount::days(45));
    }

    public function test_today_is_not_zero_days()
    {
        $this->assertSame('أقل من يوم', ArabicCount::days(0));
    }

    public function test_a_negative_count_reads_as_its_size()
    {
        $this->assertSame('3 أيام', ArabicCount::days(-3));
    }

    public function test_the_rule_works_for_any_counted_noun()
    {
        $this->assertSame('قضيتان', ArabicCount::of(2, 'قضية واحدة', 'قضيتان', 'قضايا', 'قضية'));
        $this->assertSame('5 قضايا', ArabicCount::of(5, 'قضية واحدة', 'قضيتان', 'قضايا', 'قضية'));
        $this->assertSame('20 قضية', ArabicCount::of(20, 'قضية واحدة', 'قضيتان', 'قضايا', 'قضية'));
    }
}
