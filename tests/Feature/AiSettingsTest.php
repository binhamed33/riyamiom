<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\Ai\AiManager;
use App\Services\Ai\GeminiProvider;
use App\Support\AiSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * إعدادات الذكاء الاصطناعي لكل مكتب على حدة.
 * ما تحرسه هذه الاختبارات: العزل، تشفير المفتاح، عدم تسريبه للواجهة،
 * والصلاحيات — إضافة إلى بقاء المكاتب القائمة تعمل بلا تغيير.
 */
class AiSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    public function test_key_is_stored_encrypted_not_in_plain_text(): void
    {
        $this->actingAs($this->admin())
            ->post(route('settings.ai.update'), [
                'ai_provider' => 'gemini',
                'ai_model' => 'gemini-flash-latest',
                'ai_api_key' => 'AIzaSyTESTKEY1234567890abcd',
            ])->assertRedirect();

        $row = Setting::where('key', AiSettings::KEY_API_KEY)->firstOrFail();

        $this->assertNotSame('AIzaSyTESTKEY1234567890abcd', $row->value, 'المفتاح مخزَّن كنص واضح');
        $this->assertStringNotContainsString('AIzaSyTESTKEY', $row->value);
        $this->assertSame('AIzaSyTESTKEY1234567890abcd', Crypt::decryptString($row->value));
        $this->assertSame('AIzaSyTESTKEY1234567890abcd', AiSettings::apiKey());
    }

    public function test_settings_page_never_renders_the_raw_key(): void
    {
        AiSettings::store('gemini', 'AIzaSyTESTKEY1234567890abcd', 'gemini-flash-latest');

        $page = $this->actingAs($this->admin())->get(route('settings.index'))->assertOk();

        $page->assertDontSee('AIzaSyTESTKEY1234567890abcd', false);
        $page->assertDontSee('AIzaSyTESTKEY', false);
        $page->assertSee('••••••••••••••••••••abcd', false);   // قناع بآخر أربعة محارف
    }

    public function test_empty_key_field_keeps_the_existing_key(): void
    {
        AiSettings::store('gemini', 'AIzaSyORIGINALKEY1234567', 'gemini-flash-latest');

        $this->actingAs($this->admin())->post(route('settings.ai.update'), [
            'ai_provider' => 'gemini',
            'ai_model' => 'gemini-2.5-flash',
        ])->assertRedirect();

        $this->assertSame('AIzaSyORIGINALKEY1234567', AiSettings::apiKey());
        $this->assertSame('gemini-2.5-flash', AiSettings::model());
    }

    public function test_removing_the_key_clears_only_the_key(): void
    {
        Setting::set('office_name', 'مكتب الاختبار');
        AiSettings::store('gemini', 'AIzaSyTOBEREMOVED12345678', 'gemini-flash-latest');

        $this->actingAs($this->admin())->delete(route('settings.ai.destroy'))->assertRedirect();

        $this->assertNull(Setting::get(AiSettings::KEY_API_KEY));
        $this->assertSame('gemini', AiSettings::provider());
        $this->assertSame('مكتب الاختبار', Setting::get('office_name'), 'الحذف مسّ إعدادات أخرى');
    }

    public function test_lawyer_and_staff_cannot_reach_ai_settings(): void
    {
        AiSettings::store('gemini', 'AIzaSyADMINSETKEY1234567890', 'gemini-flash-latest');

        foreach (['lawyer', 'staff'] as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true]);

            // الرفض قد يأتي 403 من طبقة الصلاحيات أو تحويلاً من طبقة أسبق —
            // المهم أن الطلب لا ينجح ولا يغيّر شيئاً
            foreach ([
                $this->actingAs($user)->post(route('settings.ai.update'), [
                    'ai_provider' => 'gemini',
                    'ai_api_key' => 'AIzaSySHOULDNOTBESAVED123',
                ]),
                $this->actingAs($user)->delete(route('settings.ai.destroy')),
                $this->actingAs($user)->post(route('settings.ai.test')),
            ] as $response) {
                $this->assertContains($response->status(), [302, 403], 'وصل مستخدم بلا صلاحية إلى إعدادات الذكاء الاصطناعي');
            }
        }

        // لا المفتاح استُبدل ولا حُذف
        $this->assertSame('AIzaSyADMINSETKEY1234567890', AiSettings::apiKey());
    }

    public function test_lawyer_does_not_see_the_ai_card_on_the_settings_page(): void
    {
        AiSettings::store('gemini', 'AIzaSyADMINSETKEY1234567890', 'gemini-flash-latest');

        $lawyer = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $response = $this->actingAs($lawyer)->get(route('settings.index'));

        $response->assertDontSee('AIzaSyADMINSETKEY', false);
        $response->assertDontSee('settings.ai.test', false);
    }

    public function test_guest_cannot_reach_ai_settings(): void
    {
        $this->post(route('settings.ai.update'), ['ai_provider' => 'gemini'])->assertRedirect(route('login'));
        $this->post(route('settings.ai.test'))->assertRedirect(route('login'));
    }

    public function test_unimplemented_provider_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('settings.ai.update'), [
                'ai_provider' => 'openai',
                'ai_api_key' => 'sk-shouldnotbeaccepted123456',
            ])
            ->assertSessionHasErrors('ai_provider');

        $this->assertNull(Setting::get(AiSettings::KEY_API_KEY));
    }

    public function test_only_implemented_providers_are_offered(): void
    {
        $available = AiSettings::availableProviders();

        $this->assertArrayHasKey('gemini', $available);
        $this->assertArrayNotHasKey('openai', $available);
        $this->assertArrayNotHasKey('anthropic', $available);
    }

    public function test_env_key_still_works_for_offices_that_never_set_one(): void
    {
        // مكتب قائم لم يفتح صفحة الإعدادات بعد — يجب ألا يتغير سلوكه
        config(['services.gemini.api_key' => 'AIzaSyLEGACYENVKEY1234567']);

        $this->assertTrue(AiSettings::isConfigured());
        $this->assertTrue(AiSettings::usingEnvFallback());
        $this->assertSame('AIzaSyLEGACYENVKEY1234567', AiSettings::apiKey());

        // وبمجرد ضبط مفتاح المكتب، يتقدّم على الموروث
        AiSettings::store('gemini', 'AIzaSyOFFICEOWNKEY12345678', null);

        $this->assertFalse(AiSettings::usingEnvFallback());
        $this->assertSame('AIzaSyOFFICEOWNKEY12345678', AiSettings::apiKey());
    }

    public function test_test_connection_reports_success_without_echoing_the_key(): void
    {
        AiSettings::store('gemini', 'AIzaSyTESTKEY1234567890abcd', 'gemini-flash-latest');
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['name' => 'models/gemini-flash-latest'], 200)]);

        $response = $this->actingAs($this->admin())->post(route('settings.ai.test'))->assertOk();

        $response->assertJson(['ok' => true]);
        $response->assertDontSee('AIzaSyTESTKEY', false);
    }

    public function test_test_connection_explains_an_invalid_key_without_leaking_it(): void
    {
        AiSettings::store('gemini', 'AIzaSyBADKEY1234567890abcd', 'gemini-flash-latest');
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => 'bad key'], 403)]);

        $response = $this->actingAs($this->admin())->post(route('settings.ai.test'))->assertOk();

        $response->assertJson(['ok' => false]);
        $this->assertStringContainsString('المفتاح غير صالح', $response->json('message'));
        $response->assertDontSee('AIzaSyBADKEY', false);
    }

    public function test_provider_resolves_to_the_office_key_and_model(): void
    {
        AiSettings::store('gemini', 'AIzaSyRESOLVEKEY123456789', 'gemini-2.5-pro');

        $provider = AiManager::provider();

        $this->assertInstanceOf(GeminiProvider::class, $provider);
        $this->assertTrue($provider->isConfigured());
        $this->assertSame('gemini-2.5-pro', AiSettings::model());
    }

    public function test_message_for_an_unconfigured_office_points_the_admin_to_settings(): void
    {
        config(['services.gemini.api_key' => null]);

        $this->actingAs($this->admin());
        $this->assertStringContainsString('الإعدادات', AiSettings::notConfiguredMessage());

        $this->actingAs(User::factory()->create(['role' => 'lawyer', 'is_active' => true]));
        $this->assertStringContainsString('مدير المكتب', AiSettings::notConfiguredMessage());
        $this->assertStringNotContainsString('.env', AiSettings::notConfiguredMessage());
    }
}
