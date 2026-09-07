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

    public function test_brand_settings_supports_dual_language_id_and_en(): void
    {
        $this->actingAs($this->adminUser);

        // Test English locale
        app()->setLocale('en');
        Livewire::test(BrandSettings::class)
            ->assertStatus(200)
            ->assertSee('Brand & Logo Settings')
            ->assertSee('Sidebar Menu Logo')
            ->assertSee('Top Navbar Logo')
            ->assertSee('Login Form Logo')
            ->assertSee('Login Hero Background Image')
            ->assertSee('Standard Profile Avatar')
            ->assertSee('Brand Identity & Colors');

        // Test Indonesian locale
        app()->setLocale('id');
        Livewire::test(BrandSettings::class)
            ->assertStatus(200)
            ->assertSee('Pengaturan Brand & Logo')
            ->assertSee('Logo Sidebar Menu')
            ->assertSee('Logo Navbar Atas')
            ->assertSee('Logo Formulir Login')
            ->assertSee('Gambar Latar Hero Login')
            ->assertSee('Avatar Profil Standar')
            ->assertSee('Identitas & Warna Brand');
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
            ->set('themeAccent', '#dc2626')
            ->call('saveDetails')
            ->assertHasNoErrors()
            ->assertSee(__('Informasi brand & warna berhasil disimpan!'));

        $this->assertEquals('PT Maju Bersama Sukses', Setting::get('brand_company_name'));
        $this->assertEquals('MBS', Setting::get('brand_company_short_name'));
        $this->assertEquals('#2563eb', Setting::get('brand_theme_primary'));
        $this->assertEquals('#1e293b', Setting::get('brand_theme_dark'));
        $this->assertEquals('#dc2626', Setting::get('brand_theme_accent'));

        $this->assertEquals('PT Maju Bersama Sukses', BrandHelper::companyName());
        $this->assertEquals('#2563eb', BrandHelper::themePrimary());
        $this->assertEquals('#dc2626', BrandHelper::themeAccent());
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

        // Test Favicon Upload & Reset
        $fakeFavicon = UploadedFile::fake()->image('custom_favicon.png', 64, 64);
        Livewire::test(BrandSettings::class)
            ->set('fileFavicon', $fakeFavicon)
            ->call('uploadLogo', 'favicon')
            ->assertHasNoErrors()
            ->assertSee(__('Logo berhasil diperbarui!'));

        $this->assertTrue(BrandHelper::isCustom('favicon'));
        $uploadedFavicon = Setting::get('brand_logo_favicon');
        $this->assertNotEmpty($uploadedFavicon);
        $this->assertFileExists(public_path($uploadedFavicon));

        Livewire::test(BrandSettings::class)
            ->call('resetLogo', 'favicon')
            ->assertSee(__('Logo dikembalikan ke konfigurasi default (.env)!'));

        $this->assertFalse(BrandHelper::isCustom('favicon'));
    }

    public function test_login_page_renders_with_budgeting_brand_assets(): void
    {
        $this->assertFileExists(public_path('assets/img/brand/logo_login.png'));
        $this->assertFileExists(public_path('assets/img/brand/login_hero.png'));
        $this->assertFileExists(public_path('assets/img/favicon/favicon.png'));
        $this->assertFileExists(public_path('favicon.ico'));

        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('assets/img/brand/logo_login.png');
        $response->assertSee('assets/img/brand/login_hero.png');
    }
}
