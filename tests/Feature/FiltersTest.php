<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\LegalCase;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * تجربة فلترة واحدة في الأقسام الأربعة، والفلاتر تعمل على الخادم
 * لا على الصفحة — فالنتائج صحيحة مهما بلغ عدد السجلات.
 */
class FiltersTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function makeCase(array $attrs = []): LegalCase
    {
        return LegalCase::create(array_merge([
            'case_number' => 'C-' . fake()->unique()->numberBetween(1000, 9999),
            'office_case_number' => 'م/' . fake()->unique()->numberBetween(100, 999),
            'title' => 'قضية اختبار',
            'description' => 'وصف',
            'type' => 'مدني',
            'court' => 'الابتدائية — مسقط',
            'opponent' => 'خصم',
            'status' => 'active',
            'priority' => 'medium',
            'opened_at' => now()->toDateString(),
        ], $attrs));
    }

    public function test_all_four_sections_use_the_same_filter_component(): void
    {
        $admin = $this->admin();

        foreach (['/cases', '/sessions', '/tasks', '/clients'] as $url) {
            $this->actingAs($admin)->get($url)
                ->assertOk()
                ->assertSee('x-ref="form"', false)          // نفس المكوّن
                ->assertSee('md-filter-sheet', false)       // ورقة سفلية على الهاتف
                ->assertSee('md-chip', false);              // رقائق الفلاتر المطبَّقة
        }
    }

    public function test_tasks_can_be_filtered_by_case(): void
    {
        $admin = $this->admin();
        $a = $this->makeCase(['title' => 'قضية ألف']);
        $b = $this->makeCase(['title' => 'قضية باء']);

        Task::create(['title' => 'مهمة على ألف', 'case_id' => $a->id, 'assigned_to' => $admin->id, 'created_by' => $admin->id, 'status' => 'pending', 'priority' => 'medium']);
        Task::create(['title' => 'مهمة على باء', 'case_id' => $b->id, 'assigned_to' => $admin->id, 'created_by' => $admin->id, 'status' => 'pending', 'priority' => 'medium']);

        $this->actingAs($admin)->get('/tasks?case_id=' . $a->id)
            ->assertOk()
            ->assertSee('مهمة على ألف', false)
            ->assertDontSee('مهمة على باء', false);
    }

    public function test_clients_can_be_filtered_by_responsible_lawyer(): void
    {
        $admin = $this->admin();
        $lawyer = User::factory()->create(['role' => 'lawyer', 'is_active' => true, 'name' => 'المحامي سالم']);

        $mine = Client::create(['name' => 'موكل سالم', 'type' => 'individual', 'phone' => '96890000001']);
        $other = Client::create(['name' => 'موكل آخر', 'type' => 'individual', 'phone' => '96890000002']);

        $this->makeCase(['client_id' => $mine->id, 'lawyer_id' => $lawyer->id]);
        $this->makeCase(['client_id' => $other->id]);

        $this->actingAs($admin)->get('/clients?lawyer_id=' . $lawyer->id)
            ->assertOk()
            ->assertSee('موكل سالم', false)
            ->assertDontSee('موكل آخر', false);
    }

    public function test_clients_can_be_filtered_by_case_activity(): void
    {
        $admin = $this->admin();

        $withActive = Client::create(['name' => 'موكل نشط', 'type' => 'individual', 'phone' => '96890000003']);
        $closedOnly = Client::create(['name' => 'موكل مغلق', 'type' => 'individual', 'phone' => '96890000004']);
        $noCases = Client::create(['name' => 'موكل بلا قضايا', 'type' => 'individual', 'phone' => '96890000005']);

        $this->makeCase(['client_id' => $withActive->id, 'status' => 'active']);
        $this->makeCase(['client_id' => $closedOnly->id, 'status' => 'closed']);

        $this->actingAs($admin)->get('/clients?activity=active')
            ->assertOk()->assertSee('موكل نشط', false)
            ->assertDontSee('موكل مغلق', false)->assertDontSee('موكل بلا قضايا', false);

        $this->actingAs($admin)->get('/clients?activity=idle')
            ->assertOk()->assertSee('موكل مغلق', false)
            ->assertDontSee('موكل نشط', false)->assertDontSee('موكل بلا قضايا', false);

        $this->actingAs($admin)->get('/clients?activity=none')
            ->assertOk()->assertSee('موكل بلا قضايا', false)
            ->assertDontSee('موكل نشط', false)->assertDontSee('موكل مغلق', false);
    }

    public function test_filters_survive_pagination(): void
    {
        $admin = $this->admin();

        // أكثر من صفحة واحدة حتى تظهر روابط الترقيم فعلاً
        for ($i = 0; $i < 20; $i++) {
            Client::create(['name' => 'شركة ' . $i, 'type' => 'company', 'phone' => '9689000' . str_pad((string) $i, 4, '0', STR_PAD_LEFT)]);
        }

        $this->actingAs($admin)->get('/clients?type=company')
            ->assertOk()
            ->assertSee('type=company', false);   // روابط الصفحات تحمل الفلتر
    }

    public function test_active_filter_count_reaches_the_panel(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/clients')->assertOk()->assertDontSee('clear_filters');

        $page = $this->actingAs($admin)->get('/clients?type=company&activity=none')->assertOk();
        $page->assertSee(__('app.clear_filters'), false);
    }

    /**
     * مِصفاة القضايا تتبع القائمة التي تُصفّيها: القائمة صارت تعرض مهام
     * المكتب كلّها، فالمِصفاة تعرض قضاياه كلّها. مِصفاة أضيق من قائمتها
     * تعني خيارات لا تُطابق ما يُعرض.
     */
    public function test_the_task_case_filter_matches_the_list_it_filters(): void
    {
        $lawyer = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $mine = $this->makeCase(['title' => 'قضيتي أنا', 'lawyer_id' => $lawyer->id]);
        $theirs = $this->makeCase(['title' => 'قضية زميل آخر']);

        $html = $this->actingAs($lawyer)->get('/tasks')->assertOk()->getContent();

        $this->assertStringContainsString('value="' . $mine->id . '"', $html);
        $this->assertStringContainsString('value="' . $theirs->id . '"', $html);
    }
}
