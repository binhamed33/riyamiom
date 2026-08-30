<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Document;
use App\Models\LegalCase;
use App\Models\Session;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * §3: المنجز موجود لا مزعج — يُطوى من القوائم اليومية ويبقى خلف زرّه،
 * ومع القضية المنجزة تُطوى جلساتها ومستنداتها ومهامها.
 * §4: ترتيب بمفاتيح معلومة على القوائم الست.
 */
class DoneToggleAndSortTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
    }

    private function caseWith(string $status, string $title): LegalCase
    {
        // القوائم تعرض اسم الموكّل — فنجعل الاسم بصمة القضية في التوكيدات
        $client = Client::create(['name' => 'موكّل ' . $title, 'phone' => '91234567', 'type' => 'individual']);

        return LegalCase::create([
            'client_id' => $client->id,
            'case_number' => 'T-' . fake()->unique()->numberBetween(1, 99999),
            'title' => $title,
            'type' => 'civil',
            'description' => 'وصف',
            'court' => 'الابتدائية',
            'opponent' => 'خصم',
            'status' => $status,
            'priority' => 'medium',
        ]);
    }

    public function test_done_cases_hide_by_default_and_appear_behind_the_button(): void
    {
        $this->caseWith('active', 'قضية جارية الآن');
        $this->caseWith('won', 'قضية كُسبت وانتهت');

        $default = $this->actingAs($this->staff)->get('/cases')->assertOk();
        $default->assertSee('موكّل قضية جارية الآن')->assertDontSee('موكّل قضية كُسبت وانتهت');

        $done = $this->actingAs($this->staff)->get('/cases?done=1')->assertOk();
        $done->assertSee('موكّل قضية كُسبت وانتهت')->assertDontSee('موكّل قضية جارية الآن');
    }

    public function test_an_explicit_status_filter_beats_the_done_gate(): void
    {
        $this->caseWith('won', 'قضية كُسبت وانتهت');

        $this->actingAs($this->staff)->get('/cases?status=won')
            ->assertOk()
            ->assertSee('موكّل قضية كُسبت وانتهت');
    }

    public function test_items_of_a_done_case_fold_with_it(): void
    {
        $activeCase = $this->caseWith('active', 'قضية جارية');
        $doneCase = $this->caseWith('closed', 'قضية مغلقة');

        Session::create(['case_id' => $activeCase->id, 'date' => now()->addDay(), 'location' => 'قاعة الجارية', 'status' => 'upcoming']);
        Session::create(['case_id' => $doneCase->id, 'date' => now()->addDay(), 'location' => 'قاعة المغلقة', 'status' => 'upcoming']);

        Document::create(['case_id' => $activeCase->id, 'title' => 'مستند الجارية', 'file_path' => 'x/a.pdf', 'file_type' => 'pdf', 'file_size' => 10, 'uploaded_by' => $this->staff->id]);
        Document::create(['case_id' => $doneCase->id, 'title' => 'مستند المغلقة', 'file_path' => 'x/b.pdf', 'file_type' => 'pdf', 'file_size' => 10, 'uploaded_by' => $this->staff->id]);

        Task::create(['case_id' => $activeCase->id, 'title' => 'مهمة الجارية', 'status' => 'pending', 'priority' => 'medium', 'assigned_to' => $this->staff->id, 'created_by' => $this->staff->id]);
        Task::create(['case_id' => $doneCase->id, 'title' => 'مهمة المغلقة', 'status' => 'pending', 'priority' => 'medium', 'assigned_to' => $this->staff->id, 'created_by' => $this->staff->id]);

        $this->actingAs($this->staff)->get('/sessions')->assertOk()
            ->assertSee('موكّل قضية جارية')->assertDontSee('موكّل قضية مغلقة');
        $this->actingAs($this->staff)->get('/sessions?done=1')->assertOk()
            ->assertSee('موكّل قضية مغلقة')->assertDontSee('موكّل قضية جارية');

        $this->actingAs($this->staff)->get('/documents')->assertOk()
            ->assertSee('مستند الجارية')->assertDontSee('مستند المغلقة');
        $this->actingAs($this->staff)->get('/documents?done=1')->assertOk()
            ->assertSee('مستند المغلقة')->assertDontSee('مستند الجارية');

        $this->actingAs($this->staff)->get('/tasks')->assertOk()
            ->assertSee('مهمة الجارية')->assertDontSee('مهمة المغلقة');
        $this->actingAs($this->staff)->get('/tasks?done=1')->assertOk()
            ->assertSee('مهمة المغلقة')->assertDontSee('مهمة الجارية');
    }

    public function test_completed_tasks_fold_even_when_their_case_is_active(): void
    {
        $case = $this->caseWith('active', 'قضية جارية');
        Task::create(['case_id' => $case->id, 'title' => 'مهمة أُنجزت', 'status' => 'completed', 'priority' => 'medium', 'assigned_to' => $this->staff->id, 'created_by' => $this->staff->id]);

        $this->actingAs($this->staff)->get('/tasks')->assertOk()->assertDontSee('مهمة أُنجزت');
        $this->actingAs($this->staff)->get('/tasks?done=1')->assertOk()->assertSee('مهمة أُنجزت');
    }

    public function test_sorting_by_name_orders_documents(): void
    {
        $case = $this->caseWith('active', 'قضية جارية');
        Document::create(['case_id' => $case->id, 'title' => 'ياء آخر الحروف', 'file_path' => 'x/y.pdf', 'file_type' => 'pdf', 'file_size' => 10, 'uploaded_by' => $this->staff->id]);
        Document::create(['case_id' => $case->id, 'title' => 'ألف أول الحروف', 'file_path' => 'x/a.pdf', 'file_type' => 'pdf', 'file_size' => 10, 'uploaded_by' => $this->staff->id]);

        $html = $this->actingAs($this->staff)->get('/documents?sort=name&dir=asc')->assertOk()->getContent();

        $this->assertLessThan(
            mb_strpos($html, 'ياء آخر الحروف'),
            mb_strpos($html, 'ألف أول الحروف'),
            'الترتيب الأبجدي الصاعد يقدّم الألف'
        );
    }

    public function test_unknown_sort_keys_fall_back_safely(): void
    {
        $this->caseWith('active', 'قضية جارية');

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($this->staff)->get('/tasks?sort=evil_column&dir=up')->assertOk();
        $this->actingAs($this->staff)->get('/clients?sort=;drop--&dir=asc')->assertOk();
        $this->actingAs($admin)->get('/audit-log?sort=x')->assertOk();
        $this->actingAs($admin)->get('/evaluations?sort=x&dir=zz')->assertOk();
    }
}
