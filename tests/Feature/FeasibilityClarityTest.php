<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\LegalCase;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * §12: دراسة الجدوى تُفهم بلا شرحٍ شفهي — لكل رسم سؤالٌ يجيب عنه
 * مكتوباً تحته، وأرقامه متاحة جدولاً، وفراغه يقول سبب فراغه.
 */
class FeasibilityClarityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    public function test_every_chart_explains_what_it_answers(): void
    {
        $this->actingAs($this->admin())->get('/feasibility')
            ->assertOk()
            ->assertSee(__('app.efficiency_comparison_help'))
            ->assertSee(__('app.monthly_case_trends_help'))
            ->assertSee(__('app.cases_by_type_help'))
            ->assertSee(__('app.team_comparison_help'));
    }

    public function test_an_empty_office_says_why_it_is_empty_instead_of_drawing_nothing(): void
    {
        $this->actingAs($this->admin())->get('/feasibility')
            ->assertOk()
            ->assertSee(__('app.no_data_yet'))
            // السكربت يذكر المعرّف دائماً ويحرسه بـif — فالفحص على العنصر
            ->assertDontSee('<canvas id="casesTypeChart"', false)
            ->assertDontSee('<canvas id="efficiencyChart"', false)
            ->assertDontSee('<canvas id="radarChart"', false);
    }

    public function test_with_real_work_the_numbers_are_readable_as_a_table(): void
    {
        $lawyer = User::factory()->create(['role' => 'lawyer', 'is_active' => true, 'name' => 'المحامي المجتهد']);
        $client = Client::create(['name' => 'موكّل الجدوى', 'phone' => '91234567', 'type' => 'individual']);

        $case = LegalCase::create([
            'client_id' => $client->id,
            'lawyer_id' => $lawyer->id,
            'case_number' => 'FS-1',
            'title' => 'قضية الجدوى',
            'type' => 'civil',
            'description' => 'وصف',
            'court' => 'الابتدائية',
            'opponent' => 'خصم',
            'status' => 'active',
            'priority' => 'medium',
        ]);

        Task::create([
            'case_id' => $case->id,
            'title' => 'مهمة الجدوى',
            'status' => 'completed',
            'priority' => 'medium',
            'assigned_to' => $lawyer->id,
            'created_by' => $lawyer->id,
        ]);

        $this->actingAs($this->admin())->get('/feasibility')
            ->assertOk()
            ->assertSee(__('app.show_numbers'))
            ->assertSee('المحامي المجتهد')
            ->assertSee('<canvas id="efficiencyChart"', false);
    }
}
