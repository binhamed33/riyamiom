<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * قائمةُ الموكّلين في حجز الموعد تُبحث بالكتابة.
 *
 * كانت قائمةً أصليّةً تُنزَل بالعجلة: مئاتُ الأسماء ولا صندوقَ بحث،
 * فمن أراد «أحمد الريامي» مرّ على عشرين أحمد. صارت كبقيّة قوائم
 * النظام (‎select.ts‎): يُكتب الاسمُ فتُصفّى — ولا تُخترع أسماءٌ فيها،
 * فالموكّلُ الجديد له تبويبُه.
 */
class AppointmentClientPickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_client_list_is_searchable_by_typing(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Client::create(['name' => 'أحمد حمود الريامي', 'type' => 'individual', 'national_id' => '1234567', 'phone' => '96891234567']);

        $html = $this->actingAs($admin)->get(route('appointments.create'))->assertOk()->getContent();

        preg_match('/<select id="client_id"[^>]*>/', $html, $m);
        $this->assertNotEmpty($m, 'لا قائمةَ موكّلين في النموذج');
        $this->assertStringContainsString('class="ts ', $m[0], 'القائمةُ أصليّةٌ لا تُبحث بالكتابة');
        $this->assertStringContainsString('data-no-create', $m[0], 'الكتابةُ الحرّة تخترع موكّلاً غيرَ مسجَّل');
        $this->assertStringContainsString('placeholder="اكتب اسم الموكّل…"', $m[0]);
    }
}
