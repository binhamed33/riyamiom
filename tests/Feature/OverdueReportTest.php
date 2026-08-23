<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\LegalCase;
use App\Models\Session;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «كشف المتأخرة» يعرض المتأخرة وحدها.
 *
 * كان الزر يكشفها ثم يعيد إلى القائمة كما هي، فيقف المستخدم أمام كل
 * القضايا يبحث عمّا كُشف للتوّ. الكشف والعرض عملٌ واحد في ذهن من
 * يضغط الزر.
 */
class OverdueReportTest extends TestCase
{
    use RefreshDatabase;

    private function developer(): User
    {
        $user = User::factory()->create(['role' => 'developer']);
        $user->is_active = true;
        $user->save();

        return $user;
    }

    private function makeCase(array $attrs = []): LegalCase
    {
        return LegalCase::factory()->create(array_merge([
            'client_id' => Client::factory()->create()->id,
            'status' => 'active',
        ], $attrs));
    }

    public function test_the_button_lands_on_a_list_filtered_to_overdue_only()
    {
        $this->makeCase(['next_date' => now()->subDays(5)]);

        $this->actingAs($this->developer())
            ->post('/cases/detect-overdue')
            ->assertRedirect(route('cases.index', ['status' => 'overdue']));
    }

    public function test_the_landing_list_carries_no_case_that_is_not_overdue()
    {
        $late = $this->makeCase(['next_date' => now()->subDays(5), 'case_number' => 'LATE-1']);
        $onTime = $this->makeCase(['next_date' => now()->addDays(5), 'case_number' => 'FINE-1']);

        $developer = $this->developer();
        $this->actingAs($developer)->post('/cases/detect-overdue');

        $response = $this->actingAs($developer)->get(route('cases.index', ['status' => 'overdue']));

        $response->assertStatus(200);
        $cases = $response->viewData('cases');

        $this->assertTrue($cases->contains('id', $late->id));
        $this->assertFalse($cases->contains('id', $onTime->id));
        $this->assertTrue($cases->every(fn ($c) => $c->status === 'overdue'));
    }

    public function test_a_case_already_overdue_still_appears_even_when_nothing_new_is_found()
    {
        $old = $this->makeCase(['status' => 'overdue']);

        $developer = $this->developer();
        $this->actingAs($developer)->post('/cases/detect-overdue');

        $cases = $this->actingAs($developer)
            ->get(route('cases.index', ['status' => 'overdue']))
            ->viewData('cases');

        $this->assertTrue($cases->contains('id', $old->id));
    }

    public function test_a_session_whose_date_has_passed_marks_its_case_overdue()
    {
        $case = $this->makeCase(['next_date' => null]);
        Session::factory()->create([
            'case_id' => $case->id,
            'status' => 'upcoming',
            'date' => now()->subDay(),
        ]);

        $this->actingAs($this->developer())->post('/cases/detect-overdue');

        $this->assertSame('overdue', $case->fresh()->status);
    }

    public function test_a_session_due_today_is_not_late_yet()
    {
        $case = $this->makeCase(['next_date' => null]);
        Session::factory()->create([
            'case_id' => $case->id,
            'status' => 'upcoming',
            'date' => now()->startOfDay(),
        ]);

        $this->actingAs($this->developer())->post('/cases/detect-overdue');

        $this->assertSame('active', $case->fresh()->status);
    }

    public function test_the_message_says_how_many_are_shown()
    {
        $this->makeCase(['next_date' => now()->subDays(3)]);

        $this->actingAs($this->developer())
            ->post('/cases/detect-overdue')
            ->assertSessionHas('success', fn ($m) => str_contains($m, '1'));
    }
}
