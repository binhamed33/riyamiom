<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Appearance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * السمات والوضع الليلي/النهاري — محفوظة لكل مستخدم على حدة.
 */
class AppearanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_are_mudawala_and_light_for_existing_users(): void
    {
        // مستخدم قائم لم يختر شيئاً — العمودان فارغان
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->assertNull($user->theme);
        $this->assertNull($user->appearance);
        $this->assertSame('mudawala', Appearance::themeKey($user));
        $this->assertSame('light', Appearance::mode($user));
    }

    public function test_a_user_can_save_a_theme_and_it_survives_a_new_session(): void
    {
        $user = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);

        $this->actingAs($user)
            ->postJson(route('appearance.update'), ['theme' => 'midnight', 'appearance' => 'dark'])
            ->assertOk()
            ->assertJson(['ok' => true, 'theme' => 'midnight', 'appearance' => 'dark']);

        $this->assertSame('midnight', $user->fresh()->theme);

        // جلسة جديدة تماماً: الصفحة تُرسم بالسمة المحفوظة من الخادم
        auth()->logout();
        $this->actingAs($user->fresh())->get('/dashboard')
            ->assertOk()
            ->assertSee('data-palette="midnight"', false)
            ->assertSee('data-theme="dark"', false);
    }

    public function test_one_user_choice_does_not_affect_another(): void
    {
        $a = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $b = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);

        $this->actingAs($a)->postJson(route('appearance.update'), ['theme' => 'burgundy'])->assertOk();

        $this->assertSame('burgundy', $a->fresh()->theme);
        $this->assertNull($b->fresh()->theme, 'اختيار مستخدم غيّر تفضيل زميله');

        $this->actingAs($b->fresh())->get('/dashboard')
            ->assertOk()
            ->assertSee('data-palette="mudawala"', false);
    }

    public function test_an_unknown_theme_is_ignored_rather_than_stored(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($user)
            ->postJson(route('appearance.update'), ['theme' => 'neon-gamer', 'appearance' => 'sepia'])
            ->assertOk()
            ->assertJson(['ok' => false, 'theme' => 'mudawala', 'appearance' => 'light']);

        $this->assertNull($user->fresh()->theme);
    }

    public function test_a_user_cannot_change_someone_elses_appearance(): void
    {
        $a = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $b = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);

        // المسار لا يقرأ أي معرّف مستخدم من الطلب
        $this->actingAs($a)->postJson(route('appearance.update'), [
            'theme' => 'slate',
            'user_id' => $b->id,
            'id' => $b->id,
        ])->assertOk();

        $this->assertSame('slate', $a->fresh()->theme);
        $this->assertNull($b->fresh()->theme);
    }

    public function test_guests_cannot_save_a_theme(): void
    {
        $this->post(route('appearance.update'), ['theme' => 'emerald'])->assertRedirect(route('login'));
    }

    public function test_every_theme_recolours_the_interface_tokens(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        foreach (array_keys(Appearance::THEMES) as $key) {
            $user->theme = $key;
            $user->save();

            $palette = Appearance::THEMES[$key];
            $html = $this->actingAs($user->fresh())->get('/dashboard')->assertOk()->getContent();

            // التدرّج يصل إلى إعداد Tailwind (الأزرار والحدود والبطاقات)
            // وإلى متغيّرات CSS (القائمة والحقول ومؤشرات التركيز)
            $this->assertStringContainsString("gold: { DEFAULT: '{$palette['DEFAULT']}'", $html, "سمة {$key} لم تصل إلى Tailwind");
            $this->assertStringContainsString("--accent: {$palette['DEFAULT']}", $html, "سمة {$key} لم تصل إلى متغيّرات CSS");
            $this->assertStringContainsString('data-palette="' . $key . '"', $html);
        }
    }

    public function test_filled_buttons_use_the_darker_shade_for_readable_white_text(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $user->theme = 'emerald';
        $user->save();

        $html = $this->actingAs($user->fresh())->get('/dashboard')->assertOk()->getContent();

        // primary هو ما يحمل نصاً أبيض، فيؤخذ من الدرجة الداكنة لا الأساسية
        $this->assertStringContainsString("primary: { DEFAULT: '" . Appearance::THEMES['emerald']['dark'] . "'", $html);
    }

    public function test_no_hardcoded_brand_gold_is_left_in_the_stylesheet(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $user->theme = 'slate';
        $user->save();

        $html = $this->actingAs($user->fresh())->get('/dashboard')->assertOk()->getContent();

        preg_match_all('/<style>(.*?)<\/style>/s', $html, $m);
        $css = implode("\n", array_slice($m[1], 1));   // نتجاوز كتلة تعريف المتغيّرات

        $this->assertSame(0, preg_match_all('/#D4AF37|rgba\(212,\s*175,\s*55/i', $css),
            'بقيت ألوان ذهبية ثابتة في CSS فلا تتبع السمة المختارة');
    }
}
