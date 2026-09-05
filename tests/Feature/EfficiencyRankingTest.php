<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\LegalCase;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * لوحةُ الترتيب تقيس العمل، لا نظافةَ الكسور.
 *
 * ═══ ما وقع على شاشة المالك ═══
 *
 * موظّفةٌ لها **قضيّةٌ واحدة** ومهمّةٌ واحدةٌ أنجزتها كانت في المركز
 * الأوّل بـ50.7٪، وموظّفةٌ تحمل **ثمانياً وخمسين قضيّة** وأربعاً
 * وعشرين جلسةً في الثاني بـ37.2٪.
 *
 * والصيغةُ كانت نسباً خالصة:
 *
 *     (نجاح×0.35) + (إنجاز×0.25) + (التزام×0.25) + (إنتاجية×0.15)
 *
 * وفيها ثلاثةُ أعطابٍ تجتمع:
 *
 * ١) **مقامٌ صفرٌ يُحسب رسوباً.** من لم يُفصَل في قضاياه بعدُ يأخذ
 *    «معدّل نجاح ٠٪» — كمن خسر كلَّ قضاياه. وهو لم يُقَس أصلاً.
 *
 * ٢) **مقامٌ واحدٌ يُعطي مئةً بالمئة.** مهمّةٌ واحدةٌ في موعدها =
 *    100٪ إنجاز + 100٪ التزام = خمسون نقطةً من مئة. ومن أنجز واحدةً
 *    من ثلاثَ عشرةَ يأخذ 7.7٪. فالعملُ الكثير يُعاقَب لأنّ مقامَه كبير.
 *
 * ٣) **الحِملُ خارج الحساب إطلاقاً.** لا قضايا ولا جلسات في الصيغة،
 *    والمتأخّراتُ وزنُها صفر. فمن لا عمل له تبقى نسبُه نظيفةً ويعلو.
 *
 * ═══ ما يحرسه هذا ═══
 *
 * ليس رقماً بعينه — فالأوزانُ تُضبط بالتجربة. بل الترتيبَ نفسَه:
 * من يحمل المكتب يسبق من لا يحمله، مهما كانت كسورُه أنظف.
 */
class EfficiencyRankingTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function lawyer(string $name): User
    {
        return User::factory()->create(['name' => $name, 'role' => 'lawyer', 'is_active' => true]);
    }

    private function casesFor(User $u, int $count, string $status = 'active'): void
    {
        $client = Client::create([
            'name' => 'موكّل', 'type' => 'individual',
            'national_id' => (string) random_int(1000000, 9999999), 'phone' => '96890000000',
        ]);

        for ($i = 0; $i < $count; $i++) {
            $n = ++$this->seq;
            LegalCase::create([
                'case_number' => 'ق/' . $n, 'office_case_number' => (string) $n, 'title' => 'قضية',
                'description' => 'و', 'type' => 'مدني', 'court' => 'محكمة', 'opponent' => 'خصم',
                'status' => $status, 'priority' => 'medium',
                'client_id' => $client->id, 'lawyer_id' => $u->id,
                'created_by' => $u->id, 'opened_at' => now()->subMonths(2),
            ]);
        }
    }

    private function tasksFor(User $u, int $total, int $done, int $onTime): void
    {
        for ($i = 0; $i < $total; $i++) {
            $isDone = $i < $done;
            Task::create([
                'title' => 'مهمّة ' . (++$this->seq),
                'status' => $isDone ? 'completed' : 'pending',
                'priority' => 'medium',
                'assigned_to' => $u->id,
                'created_by' => $u->id,
                'due_date' => now()->subDays(5),
                'completed_at' => $isDone ? ($i < $onTime ? now()->subDays(6) : now()->subDay()) : null,
            ]);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function board(): array
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $data = $this->actingAs($admin)->get(route('feasibility.index'))
            ->assertOk()
            ->viewData('efficiencyData');

        return $data->map(fn ($r) => [
            'name' => $r['user']->name,
            'overall' => $r['overall'],
            'cases' => $r['total_cases'],
        ])->all();
    }

    /**
     * الحالةُ التي وقعت بالضبط: واحدةٌ بقضيّةٍ وأخرى بثمانٍ وخمسين.
     */
    public function test_the_lawyer_carrying_the_office_outranks_the_one_with_a_single_case(): void
    {
        $light = $this->lawyer('صاحبةُ القضيّة الواحدة');
        $this->casesFor($light, 1);
        $this->tasksFor($light, total: 1, done: 1, onTime: 1);

        $heavy = $this->lawyer('حاملةُ المكتب');
        $this->casesFor($heavy, 40);
        $this->casesFor($heavy, 2, 'won');
        $this->tasksFor($heavy, total: 13, done: 1, onTime: 0);

        $board = $this->board();
        $names = array_column($board, 'name');

        $this->assertSame('حاملةُ المكتب', $names[0],
            'صاحبةُ القضيّة الواحدة ما زالت تسبق من يحمل اثنتين وأربعين — '
            . json_encode($board, JSON_UNESCAPED_UNICODE));
    }

    /**
     * ومن لا قضيّةَ له ولا جلسةَ ولا مهمّةَ منجزة لا يعلو على من يعمل.
     *
     * كان يأخذ صفراً أو رقماً من نسبٍ فارغة، ويسبق أحياناً من يحمل
     * ثمانيَ عشرةَ قضيّة.
     */
    public function test_someone_with_no_work_never_outranks_someone_with_work(): void
    {
        $idle = $this->lawyer('بلا عمل');
        $this->tasksFor($idle, total: 2, done: 0, onTime: 0);

        $working = $this->lawyer('يعمل');
        $this->casesFor($working, 18);

        $board = $this->board();
        $rank = array_flip(array_column($board, 'name'));

        $this->assertLessThan($rank['بلا عمل'], $rank['يعمل'],
            'من لا عمل له سبق من يحمل ثماني عشرة قضيّة — '
            . json_encode($board, JSON_UNESCAPED_UNICODE));
    }

    /**
     * ومقامٌ واحدٌ لا يُعطي درجةً كاملة.
     *
     * مهمّةٌ واحدةٌ في موعدها كانت 100٪ إنجازٍ و100٪ التزام. والنسبةُ
     * تُشَدّ الآن نحو متوسّط المكتب بقدر ضعف عيّنتها.
     */
    public function test_a_sample_of_one_does_not_score_as_perfection(): void
    {
        $one = $this->lawyer('عيّنةٌ واحدة');
        $this->casesFor($one, 1);
        $this->tasksFor($one, total: 1, done: 1, onTime: 1);

        $many = $this->lawyer('عيّنةٌ كبيرة');
        $this->casesFor($many, 1);
        $this->tasksFor($many, total: 20, done: 18, onTime: 18);

        $board = $this->board();
        $rank = array_flip(array_column($board, 'name'));

        $this->assertLessThan($rank['عيّنةٌ واحدة'], $rank['عيّنةٌ كبيرة'],
            'من أنجز مهمّةً واحدةً سبق من أنجز ثمانيَ عشرةَ من عشرين — '
            . json_encode($board, JSON_UNESCAPED_UNICODE));
    }

    /**
     * والمتأخّراتُ تكلّف.
     *
     * كانت وزنُها صفراً: اثنتا عشرةَ مهمّةً متأخّرةً لا تُنقص نقطةً.
     */
    public function test_overdue_tasks_cost_something(): void
    {
        $clean = $this->lawyer('بلا تأخير');
        $this->casesFor($clean, 10);
        $this->tasksFor($clean, total: 4, done: 4, onTime: 4);

        $late = $this->lawyer('متأخّر');
        $this->casesFor($late, 10);
        $this->tasksFor($late, total: 4, done: 4, onTime: 4);
        // ثمانيةُ متأخّراتٍ فوق ذلك — غيرُ منجزةٍ وموعدُها مضى
        $this->tasksFor($late, total: 8, done: 0, onTime: 0);

        $board = $this->board();
        $rank = array_flip(array_column($board, 'name'));

        $this->assertLessThan($rank['متأخّر'], $rank['بلا تأخير'],
            'المتأخّراتُ لم تكلّف شيئاً — ' . json_encode($board, JSON_UNESCAPED_UNICODE));
    }

    /** ولا رقمَ يتجاوز المئة ولا ينزل تحت الصفر. */
    public function test_the_score_stays_a_percentage(): void
    {
        $u = $this->lawyer('كثيرُ العمل');
        $this->casesFor($u, 5, 'won');
        $this->tasksFor($u, total: 30, done: 30, onTime: 30);

        foreach ($this->board() as $row) {
            $this->assertGreaterThanOrEqual(0, $row['overall'], $row['name']);
            $this->assertLessThanOrEqual(100, $row['overall'], $row['name']);
        }
    }
}
