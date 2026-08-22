<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\LegalCase;
use App\Models\User;
use Tests\TestCase;

class CreateCaseWithSessionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => '127.0.0.1',
            'database.connections.mysql.port' => '3306',
            'database.connections.mysql.database' => 'mudawala',
            'database.connections.mysql.username' => 'mudawala',
            'database.connections.mysql.password' => env('DB_PASSWORD', ''),
        ]);
    }

    public function test_store_creates_case_with_sessions(): void
    {
        $user = User::where('role', 'developer')->firstOrFail();
        $client = Client::firstOrFail();
        $number = 'TEST-' . time();

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
