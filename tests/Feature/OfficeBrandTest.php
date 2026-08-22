<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\OfficeBrand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OfficeBrandTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    public function test_admin_uploads_office_logo_and_it_is_served(): void
    {
        Storage::fake('local');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('settings.logo.update'), [
            'logo' => UploadedFile::fake()->image('my-office.png', 240, 240),
        ])->assertRedirect();

        Storage::disk('local')->assertExists('office/logo.png');
        $this->assertSame('office/logo.png', Setting::get(OfficeBrand::KEY_PATH));
        $this->assertNotNull(OfficeBrand::logoUrl());

        // يُقدَّم للزوار أيضاً لأنه يظهر في شاشة الدخول
        $this->get(route('office.logo'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_logo_replaces_previous_file_without_leftovers(): void
    {
        Storage::fake('local');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('settings.logo.update'), ['logo' => UploadedFile::fake()->image('a.png')]);
        $this->actingAs($admin)->post(route('settings.logo.update'), ['logo' => UploadedFile::fake()->image('b.jpg')]);

        Storage::disk('local')->assertMissing('office/logo.png');
        Storage::disk('local')->assertExists('office/logo.jpg');
        $this->assertSame('office/logo.jpg', Setting::get(OfficeBrand::KEY_PATH));
    }

    public function test_delete_removes_logo_and_falls_back_to_product_identity(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('settings.logo.update'), ['logo' => UploadedFile::fake()->image('a.png')]);

        $this->actingAs($admin)->delete(route('settings.logo.destroy'))->assertRedirect();

        Storage::disk('local')->assertMissing('office/logo.png');
        $this->assertNull(OfficeBrand::logoUrl());
        $this->get(route('office.logo'))->assertNotFound();
    }

    public function test_rejects_non_image_and_oversized_files(): void
    {
        Storage::fake('local');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('settings.logo.update'), [
            'logo' => UploadedFile::fake()->create('payload.php', 10, 'application/x-php'),
        ])->assertSessionHasErrors('logo');

        $this->actingAs($admin)->post(route('settings.logo.update'), [
            'logo' => UploadedFile::fake()->create('huge.png', OfficeBrand::MAX_KB + 200, 'image/png'),
        ])->assertSessionHasErrors('logo');

        Storage::disk('local')->assertMissing('office/logo.png');
    }

    public function test_lawyer_cannot_change_office_identity(): void
    {
        Storage::fake('local');
        $lawyer = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);

        $this->actingAs($lawyer)->post(route('settings.logo.update'), [
            'logo' => UploadedFile::fake()->image('x.png'),
        ])->assertRedirect(route('dashboard'));

        Storage::disk('local')->assertMissing('office/logo.png');
    }

    public function test_path_setting_cannot_point_outside_the_office_folder(): void
    {
        Storage::fake('local');
        // محاولة تسريب مسار خارجي عبر الإعدادات لا تُقبل إطلاقاً
        Setting::set(OfficeBrand::KEY_PATH, '../../../.env', 'general');
        $this->assertNull(OfficeBrand::storedPath());
        $this->assertNull(OfficeBrand::logoUrl());

        Setting::set(OfficeBrand::KEY_PATH, 'office/../secrets.png', 'general');
        $this->assertNull(OfficeBrand::storedPath());

        $this->get(route('office.logo'))->assertNotFound();
    }

    public function test_office_name_and_logo_appear_in_the_interface(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        Setting::set('office_name', 'مكتب الاختبار للمحاماة', 'general');
        $this->actingAs($admin)->post(route('settings.logo.update'), ['logo' => UploadedFile::fake()->image('a.png')]);

        $this->actingAs($admin)->get(route('settings.index'))
            ->assertOk()
            ->assertSee('هوية المكتب', false)
            ->assertSee('مكتب الاختبار للمحاماة', false);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('office.logo'), false);
    }
}
