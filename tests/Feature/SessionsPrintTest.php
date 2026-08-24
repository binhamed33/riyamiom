<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\LegalCase;
use App\Models\Session;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * §30: جدول جلسات يُطبع — بنفس تصفية الشاشة، وبهوية المكتب.
 *
 * الفائدة كلها في أن الورقة تطابق ما صفّاه الموظف: من طبع «جلسات
 * الأسبوع» يحمل جلسات الأسبوع إلى المحكمة، لا كل جلسات المكتب.
 */
class SessionsPrintTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
    }

    private function makeSession(array $caseAttrs, string $date, string $status = 'upcoming'): Session
    {
        $client = Client::create(['name' => 'موكّل ' . fake()->unique()->numberBetween(1, 9999), 'phone' => '91234567', 'type' => 'individual']);

        $case = LegalCase::create(array_merge([
            'client_id' => $client->id,
            'case_number' => 'P-' . fake()->unique()->numberBetween(1, 99999),
            'title' => 'قضية للطباعة',
            'type' => 'civil',
            'description' => 'وصف',
            'court' => 'الابتدائية',
            'opponent' => 'خصم',
            'status' => 'active',
            'priority' => 'medium',
        ], $caseAttrs));

        return Session::create([
            'case_id' => $case->id,
            'date' => $date,
            'location' => 'قاعة ٣',
            'status' => $status,
        ]);
    }

    public function test_the_print_sheet_carries_the_office_identity_and_the_rows(): void
    {
        $this->makeSession(['title' => 'نزاع عقد مقاولة'], now()->addDays(2)->toDateTimeString());

        $html = $this->actingAs($this->staff())->get('/sessions/print')->assertOk()->getContent();

        $this->assertStringContainsString('جدول الجلسات', $html);
        $this->assertStringContainsString(\App\Support\OfficeBrand::name(), $html);
        $this->assertStringContainsString('نزاع عقد مقاولة', $html);
        $this->assertStringContainsString('الابتدائية', $html);
    }

    public function test_the_sheet_obeys_the_same_filters_as_the_screen(): void
    {
        $this->makeSession(['title' => 'قضية هذا الأسبوع'], now()->addDay()->toDateTimeString());
        $this->makeSession(['title' => 'قضية بعد شهرين'], now()->addMonths(2)->toDateTimeString());

        $html = $this->actingAs($this->staff())->get('/sessions/print?range=week')->assertOk()->getContent();

        $this->assertStringContainsString('قضية هذا الأسبوع', $html);
        $this->assertStringNotContainsString('قضية بعد شهرين', $html, 'الورقة تجاوزت تصفية الشاشة');
        $this->assertStringContainsString('هذا الأسبوع', $html, 'الورقة تذكر التصفية المطبَّقة');
    }

    public function test_the_lawyer_filter_reaches_the_sheet(): void
    {
        $mine = $this->staff();
        $other = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);

        $this->makeSession(['title' => 'قضيتي أنا', 'lawyer_id' => $mine->id], now()->addDay()->toDateTimeString());
        $this->makeSession(['title' => 'قضية زميلي', 'lawyer_id' => $other->id], now()->addDay()->toDateTimeString());

        $html = $this->actingAs($mine)->get('/sessions/print?mine=1')->assertOk()->getContent();

        $this->assertStringContainsString('قضيتي أنا', $html);
        $this->assertStringNotContainsString('قضية زميلي', $html);
    }

    public function test_an_empty_result_says_so_instead_of_an_empty_table(): void
    {
        $html = $this->actingAs($this->staff())->get('/sessions/print?range=today')->assertOk()->getContent();

        $this->assertStringContainsString('لا جلسات ضمن هذه التصفية', $html);
    }

    public function test_a_client_account_cannot_print_the_office_schedule(): void
    {
        $client = User::factory()->create(['role' => 'client', 'is_active' => true]);

        $this->actingAs($client)->get('/sessions/print')->assertRedirect();
    }

    public function test_print_does_not_collide_with_the_show_route(): void
    {
        // لولا ترتيب المسارات لالتقط sessions/{session} كلمة print معرّفاً
        $session = $this->makeSession(['title' => 'قضية'], now()->addDay()->toDateTimeString());

        $this->actingAs($this->staff())->get('/sessions/print')->assertOk();
        $this->actingAs($this->staff())->get('/sessions/' . $session->id)->assertOk();
    }
}
