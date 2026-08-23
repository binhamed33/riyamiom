<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * عيبا الهاتف (§16 و§17): وميض الشريط الجانبي وزرّ المساعد الحاجب.
 *
 * قبل أن يستيقظ Alpine لم يكن للشريط الجانبي تحويلٌ يخرجه من الشاشة،
 * وكانت طبقة التعتيم بلا x-cloak — فمع كل تنقّل من الشريط السفلي
 * «تفتح» لوحة من الجانب ثم تختفي. وزرّ المساعد كان على bottom-6
 * فوق الشريط السفلي نفسه يحجب آخر عنصر فيه.
 */
class MobileLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function page(): string
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        return $this->actingAs($admin)->get('/dashboard')->assertOk()->getContent();
    }

    public function test_the_sidebar_is_cloaked_off_canvas_until_alpine_wakes(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('data-mobile-cloak', $html);
        $this->assertStringContainsString("aside[data-mobile-cloak] { transform: translateX(100%)", $html);
    }

    public function test_the_dark_overlay_cannot_exist_before_alpine(): void
    {
        $html = $this->page();

        $overlay = strpos($html, 'bg-black/45');
        $this->assertNotFalse($overlay, 'طبقة التعتيم غائبة');

        $chunk = substr($html, max(0, $overlay - 700), 700);
        $this->assertStringContainsString('x-cloak', $chunk, 'طبقة التعتيم بلا x-cloak — ستظهر قبل Alpine');
    }

    public function test_the_ai_button_sits_above_the_bottom_nav_on_mobile(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('bottom-24 md:bottom-6', $html, 'زرّ المساعد يجب أن يرتفع فوق الشريط السفلي في الهاتف');
        $this->assertStringNotContainsString('"fixed bottom-6 left-6 z-40 w-14', $html);
    }
}
