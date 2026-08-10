<?php

namespace Tests\Feature;

use App\Models\CaseActivity;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_case_activity_belongs_to_case_and_user()
    {
        $developer = User::factory()->create(['role' => 'developer', 'is_active' => true]);
        $case = LegalCase::factory()->create();

        $activity = CaseActivity::create([
            'case_id' => $case->id,
            'user_id' => $developer->id,
            'type' => CaseActivity::TYPE_NOTE,
            'title' => 'مكالمة مع الموكل',
            'content' => 'نوقش تأجيل الجلسة',
            'occurred_at' => now(),
        ]);

        $this->assertDatabaseHas('case_activities', [
            'case_id' => $case->id,
            'user_id' => $developer->id,
            'type' => 'note',
            'title' => 'مكالمة مع الموكل',
        ]);
        $this->assertInstanceOf(LegalCase::class, $activity->case);
        $this->assertInstanceOf(User::class, $activity->user);
    }

    public function test_case_activity_cascade_deletes_with_case()
    {
        $developer = User::factory()->create(['role' => 'developer', 'is_active' => true]);
        $case = LegalCase::factory()->create();

        CaseActivity::create([
            'case_id' => $case->id,
            'user_id' => $developer->id,
            'type' => CaseActivity::TYPE_CALL,
            'title' => 'اتصال',
        ]);

        $case->forceDelete();

        $this->assertDatabaseMissing('case_activities', ['title' => 'اتصال']);
    }
}