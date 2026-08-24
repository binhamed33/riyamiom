<?php

namespace Tests\Feature;

use App\Models\HrPerformance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * صفحة الموارد البشرية كانت تستعلم لكل موظّف على حدة داخل حلقة:
 * فريقٌ من عشرين موظفاً يفتح صفحته بـ٣٩٤ استعلاماً. الاستعلامات
 * المجمَّعة أنزلتها إلى ٧٤ — والاختبار يمنع عودة الحلقة.
 *
 * العدد المسموح سخيّ عمداً: الغرض منع النمو الخطّي مع عدد الموظفين،
 * لا تجميد رقمٍ بعينه يكسره أي تعديل بريء في الصفحة.
 */
class HrPerformanceQueryTest extends TestCase
{
    use RefreshDatabase;

    /** يضيف موظفين بتقييماتهم — بلا مسح ما سبق. */
    private function addTeam(User $admin, int $count): void
    {
        $team = User::factory()->count($count)->create(['role' => 'lawyer', 'is_active' => true]);

        foreach ($team as $i => $emp) {
            HrPerformance::create([
                'employee_id' => $emp->id,
                'reviewer_id' => $admin->id,
                'rating' => 3 + ($i % 3) * 0.5,
                'review_date' => now()->toDateString(),
            ]);
        }
    }

    private function queriesForHrPage(User $admin): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($admin)->get('/hr')->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_the_page_does_not_grow_query_by_query_with_the_team(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->addTeam($admin, 3);
        $small = $this->queriesForHrPage($admin);

        // نفس الصفحة بعد أن كبر الفريق ستة عشر موظفاً
        $this->addTeam($admin, 16);
        $large = $this->queriesForHrPage($admin);

        $growth = $large - $small;

        $this->assertLessThan(
            40,
            $growth,
            "‏١٦ موظفاً إضافياً كلّفوا {$growth} استعلاماً — عادت الحلقة إلى الصفحة"
        );
    }
}
