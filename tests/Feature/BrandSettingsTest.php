<?php

namespace Tests\Feature;

use App\Helpers\BrandHelper;
use App\Livewire\Settings\BrandSettings;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BrandSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndUserSeeder::class);

        $this->adminUser = User::where('email', config('rkap.admin_email', 'admin@rkap.com'))->first() ?? User::first();

        $roleUser = Role::where('name', 'user')->first();
        $this->regularUser = User::create([
            'name'     => 'Regular User',
            'email'    => 'regular@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->regularUser->assignRole($roleUser);
    }

    public function test_unauthenticated_user_cannot_access_brand_settings(): void
    {
        $response = $this->get('/settings/brand');
        $response->assertRedirect('/login');
    }

    public function test_regular_user_cannot_access_brand_settings(): void
    {
        $this->actingAs($this->regularUser);
        $response = $this->get('/settings/brand');
        $response->assertStatus(403);
    }

    public function test_admin_can_access_brand_settings_page(): void
    {
        $this->actingAs($this->adminUser);
        $response = $this->get('/settings/brand');
        $response->assertStatus(200);
    }

    public function test_brand_settings_renders_and_loads_defaults(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(BrandSettings::class)
            ->assertStatus(200)
            ->assertSee(__('Pengaturan Brand & Logo'))
            ->assertSee(__('Logo Sidebar Menu'))
            ->assertSee(__('Logo Navbar Atas'))
            ->assertSee(__('Logo Formulir Login'))
            ->assertSee(__('Gambar Latar Hero Login'))
            ->assertSee(__('Avatar Profil Standar'));
    }

    public function test_admin_can_update_company_details_and_colors(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(BrandSettings::class)
            ->set('companyName', 'PT Maju Bersama Sukses')
            ->set('companyShortName', 'MBS')
            ->set('companyTagline', 'Sistem Penganggaran Terpadu')
            ->set('themePrimary', '#2563eb')
            ->set('themeDark', '#1e293b')
            ->call('saveDetails')
            ->assertHasNoErrors()
            ->assertSee(__('Informasi brand & warna berhasil disimpan!'));

        $this->assertEquals('PT Maju Bersama Sukses', Setting::get('brand_company_name'));
        $this->assertEquals('MBS', Setting::get('brand_company_short_name'));
        $this->assertEquals('#2563eb', Setting::get('brand_theme_primary'));
        $this->assertEquals('#1e293b', Setting::get('brand_theme_dark'));

        $this->assertEquals('PT Maju Bersama Sukses', BrandHelper::companyName());
        $this->assertEquals('#2563eb', BrandHelper::themePrimary());
    }

    public function test_admin_can_upload_and_reset_logo(): void
    {
        $this->actingAs($this->adminUser);

        $fakeImage = UploadedFile::fake()->image('custom_sidebar.png', 200, 50);

        Livewire::test(BrandSettings::class)
            ->set('fileSidebar', $fakeImage)
            ->call('uploadLogo', 'sidebar')
            ->assertHasNoErrors()
            ->assertSee(__('Logo berhasil diperbarui!'));

        $this->assertTrue(BrandHelper::isCustom('sidebar'));
        $uploadedPath = Setting::get('brand_logo_sidebar');
        $this->assertNotEmpty($uploadedPath);
        $this->assertFileExists(public_path($uploadedPath));

        // Test Reset
        Livewire::test(BrandSettings::class)
            ->call('resetLogo', 'sidebar')
            ->assertSee(__('Logo dikembalikan ke konfigurasi default (.env)!'));

        $this->assertFalse(BrandHelper::isCustom('sidebar'));
        $this->assertNull(Setting::get('brand_logo_sidebar'));
    }
}
