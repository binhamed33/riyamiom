<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_for_authenticated_user()
    {
        $developer = User::factory()->create(['role' => 'developer', 'is_active' => true]);

        $response = $this->actingAs($developer)->get('/dashboard');

        $response->assertStatus(200);
        // العنوان يأتي من ملف اللغة — الاختبارات تعمل بالإنجليزية،
        // فمقارنته بنصّ عربي مكتوب حرفياً تسقط بلا سبب حقيقي.
        $response->assertSee(__('app.page_dashboard'));
    }

    /**
     * والموكّلُ يُصرَف إلى صفحاته — لا تُخدَم له لوحةُ المكتب.
     *
     * كان الاختبارُ يشترط ٢٠٠، فوثّق العطبَ لا الصواب: كلُّ استعلامات
     * اللوحة مقيَّدةٌ بـ«when($isLawyer)» وحدَها، فتعمل على المكتب
     * كلِّه لكلّ دورٍ آخر. فكان حسابُ موكّلٍ يقرأ في صفحته الأولى
     * أسماءَ موكّلين آخرين وأرقامَ هواتفهم وعناوينَ قضاياهم.
     *
     * والصرفُ لا المنع: ‎/ يحوّل إلى /dashboard لكلّ أحد، فردُّ ٤٠٣
     * يكسر دخولَ الموكّل.
     */
    public function test_dashboard_sends_a_client_to_their_own_pages()
    {
        $client = User::factory()->create(['role' => 'client', 'is_active' => true]);

        $this->actingAs($client)->get('/dashboard')
            ->assertRedirect(route('client.cases'));
    }

    public function test_dashboard_requires_auth()
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_dashboard_shows_today_brief_for_team()
    {
        $developer = User::factory()->create(['role' => 'developer', 'is_active' => true]);

        $response = $this->actingAs($developer)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('ما يحتاج انتباهك اليوم');
        $response->assertSee('عرض كل شيء');
    }
}