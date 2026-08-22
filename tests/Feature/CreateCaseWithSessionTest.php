<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * كان هذا الملف يوجّه نفسه إلى قاعدة تطوير حيّة على MySQL فلا يعمل إلا
 * على جهاز واحد. صار يبني ما يحتاجه بنفسه على قاعدة الاختبار.
 */
class CreateCaseWithSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_case_with_sessions(): void
    {
        $user = User::factory()->create(['role' => 'developer', 'is_active' => true]);
        $client = Client::factory()->create();
        $number = 'TEST-CASE-1';

        $response = $this->actingAs($user)->post('/cases', [
            'case_number' => $number,
            'case_type'   => 'تحكيم تجاري',
            'title'       => 'اختبار',
            'description' => 'وصف تجريبي',
            'court'       => 'المحكمة العليا',
            'opponent'    => 'خصم تجريبي',
            'status'      => 'pending',
            'priority'    => 'medium',
            'client_id'   => $client->id,
            'sessions'    => [
                [
                    'date'     => '2026-08-02T10:00',
                    'location' => 'المحكمة الابتدائية',
                    'status'   => 'upcoming',
                    'notes'    => 'ملاحظة',
                    'report'   => 'قرار',
                ],
            ],
        ]);

        $response->assertRedirect();

        $case = LegalCase::where('case_number', $number)->first();
        $this->assertNotNull($case, 'Case was not created');
        $this->assertEquals('تحكيم تجاري', $case->case_type, 'Custom case type was not saved');
        $this->assertEquals(1, $case->sessions()->count(), 'Sessions were not saved with the case');

        $case->delete();
    }
}
