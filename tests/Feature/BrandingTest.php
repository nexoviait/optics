<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $admin = User::factory()->create();
        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();
        $this->actingAs($admin);
    }

    public function test_admin_uploads_logo_and_favicon_and_storefront_uses_them(): void
    {
        $this->post(route('admin.settings.branding.update'), [
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
            'favicon' => UploadedFile::fake()->image('favicon.png', 64, 64),
        ])->assertRedirect(route('admin.settings.index', ['tab' => 'branding']));

        $logo = Settings::get('logo_path');
        $favicon = Settings::get('favicon_path');
        Storage::disk('public')->assertExists([$logo, $favicon]);

        $this->get('/')->assertOk()
            ->assertSee(Storage::disk('public')->url($logo), false)
            ->assertSee('<link rel="icon" href="'.Storage::disk('public')->url($favicon).'">', false);
        $this->get('/admin/settings?tab=branding')->assertOk();
    }

    public function test_replacing_a_logo_deletes_the_old_file(): void
    {
        $this->post(route('admin.settings.branding.update'), ['logo' => UploadedFile::fake()->image('a.png', 100, 100)]);
        $old = Settings::get('logo_path');

        $this->post(route('admin.settings.branding.update'), ['logo' => UploadedFile::fake()->image('b.png', 100, 100)]);

        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists(Settings::get('logo_path'));
    }

    public function test_svg_and_oversized_files_are_rejected(): void
    {
        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $this->post(route('admin.settings.branding.update'), ['logo' => $svg])->assertSessionHasErrors('logo');
        $this->post(route('admin.settings.branding.update'), ['favicon' => UploadedFile::fake()->image('f.png')->size(900)])->assertSessionHasErrors('favicon');
        $this->assertNull(Settings::get('logo_path'));
    }

    public function test_restoring_default_removes_the_upload(): void
    {
        $this->post(route('admin.settings.branding.update'), ['favicon' => UploadedFile::fake()->image('f.png', 32, 32)]);
        $path = Settings::get('favicon_path');

        $this->delete(route('admin.settings.branding.destroy', 'favicon'))->assertRedirect();

        Storage::disk('public')->assertMissing($path);
        $this->assertNull(Settings::get('favicon_path'));
        $this->get('/')->assertSee(asset('favicon.ico'), false);
    }

    public function test_customers_cannot_change_branding(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.settings.branding.update'), ['logo' => UploadedFile::fake()->image('x.png', 100, 100)])
            ->assertForbidden();
    }
}
