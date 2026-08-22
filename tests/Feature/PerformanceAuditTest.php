<?php

namespace Tests\Feature;

use App\Models\LegalCase;
use App\Models\Client;
use App\Models\Document;
use App\Models\Session;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * تدقيق أداء الصفحات الرئيسية.
 *
 * ما يحرسه: أن عدد الاستعلامات لا ينمو مع عدد الصفوف. صفحة تُصدر
 * استعلاماً لكل قضية تعمل تماماً على عشر قضايا وتخنق مكتباً فيه ألف.
 */
class PerformanceAuditTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'developer', 'is_active' => true]);
    }

    /** يبني عدداً من القضايا الكاملة (عميل + جلسات + مهام + مستندات). */
    private function seedCases(int $cases): void
    {
        for ($i = 0; $i < $cases; $i++) {
            $client = Client::factory()->create();
            $case = LegalCase::factory()->create(['client_id' => $client->id]);
            Session::factory()->create(['case_id' => $case->id]);
            Task::factory()->create(['case_id' => $case->id]);
            Document::factory()->create(['case_id' => $case->id, 'access_level' => 'all']);
        }
    }

    /** @return int عدد الاستعلامات التي أصدرتها الصفحة */
    private function queriesFor(string $url, User $user): int
    {
        $count = 0;
        DB::listen(function () use (&$count) { $count++; });

        $this->actingAs($user)->get($url)->assertSuccessful();

        DB::flushQueryLog();

        return $count;
    }

    public static function pages(): array
    {
        return [
            'cases' => ['/cases'],
            'clients' => ['/clients'],
            'sessions' => ['/sessions'],
            'tasks' => ['/tasks'],
            'documents' => ['/documents'],
            'dashboard' => ['/dashboard'],
            'attention' => ['/attention'],
            'audit log' => ['/audit-log'],
            'notifications' => ['/notifications'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function test_a_page_does_not_issue_a_query_per_row(string $url): void
    {
        $admin = $this->admin();

        $this->seedCases(3);
        $small = $this->queriesFor($url, $admin);

        $this->seedCases(9);   // أربعة أضعاف الصفوف
        $large = $this->queriesFor($url, $admin);

        $this->assertLessThanOrEqual(
            $small + 3,
            $large,
            "{$url}: عدد الاستعلامات قفز من {$small} إلى {$large} حين زادت الصفوف — استعلام لكل صف."
        );
    }

    /** صفحات التفاصيل: قضية واحدة بكل ما يتعلّق بها. */
    public function test_a_case_page_does_not_issue_a_query_per_related_row(): void
    {
        $admin = $this->admin();
        $case = LegalCase::factory()->create(['client_id' => Client::factory()->create()->id]);

        Session::factory()->count(2)->create(['case_id' => $case->id]);
        Task::factory()->count(2)->create(['case_id' => $case->id]);
        Document::factory()->count(2)->create(['case_id' => $case->id, 'access_level' => 'all']);
        $small = $this->queriesFor("/cases/{$case->id}", $admin);

        Session::factory()->count(6)->create(['case_id' => $case->id]);
        Task::factory()->count(6)->create(['case_id' => $case->id]);
        Document::factory()->count(6)->create(['case_id' => $case->id, 'access_level' => 'all']);
        $large = $this->queriesFor("/cases/{$case->id}", $admin);

        $this->assertLessThanOrEqual(
            $small + 3,
            $large,
            "صفحة القضية: الاستعلامات قفزت من {$small} إلى {$large} — استعلام لكل صف."
        );
    }

    public function test_no_page_loads_a_relation_lazily(): void
    {
        Model::preventLazyLoading(true);

        $admin = $this->admin();
        $this->seedCases(4);

        $failures = [];

        foreach (array_column(self::pages(), 0) as $url) {
            try {
                $this->actingAs($admin)->get($url)->assertSuccessful();
            } catch (\Illuminate\Database\LazyLoadingViolationException $e) {
                $failures[] = $url . ': ' . $e->getMessage();
            }
        }

        Model::preventLazyLoading(false);

        $this->assertSame([], $failures, implode("\n", $failures));
    }
}
