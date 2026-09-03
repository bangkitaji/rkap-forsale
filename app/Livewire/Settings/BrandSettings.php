<?php

namespace App\Livewire\Settings;

use App\Helpers\BrandHelper;
use App\Models\Setting;
use Livewire\Component;
use Livewire\WithFileUploads;

class BrandSettings extends Component
{
    use WithFileUploads;

    // Temporary uploaded files
    public $fileSidebar;
    public $fileNavbar;
    public $fileLogin;
    public $fileLoginHero;
    public $fileAvatar;

    // Company Information
    public string $companyName = '';
    public string $companyShortName = '';
    public string $companyTagline = '';

    // Theme Colors
    public string $themePrimary = '#960b10';
    public string $themeDark = '#1a1f5e';

    public function mount(): void
    {
        $this->loadSettings();
    }

    public function loadSettings(): void
    {
        $this->companyName = BrandHelper::companyName();
        $this->companyShortName = BrandHelper::companyShortName();
        $this->companyTagline = BrandHelper::companyTagline();
        $this->themePrimary = BrandHelper::themePrimary();
        $this->themeDark = BrandHelper::themeDark();
    }

    /**
     * Save text and color settings.
     */
    public function saveDetails(): void
    {
        $this->validate([
            'companyName'      => 'required|string|max:150',
            'companyShortName' => 'required|string|max:50',
            'companyTagline'   => 'nullable|string|max:255',
            'themePrimary'     => ['required', 'regex:/^#([a-f0-9]{6}|[a-f0-9]{3})$/i'],
            'themeDark'        => ['required', 'regex:/^#([a-f0-9]{6}|[a-f0-9]{3})$/i'],
        ]);

        Setting::set('brand_company_name', $this->companyName);
        Setting::set('brand_company_short_name', $this->companyShortName);
        Setting::set('brand_company_tagline', $this->companyTagline);
        Setting::set('brand_theme_primary', $this->themePrimary);
        Setting::set('brand_theme_dark', $this->themeDark);

        session()->flash('success_details', __('Informasi brand & warna berhasil disimpan!'));
    }

    /**
     * Upload and save a specific logo.
     */
    public function uploadLogo(string $type): void
    {
        $propMap = [
            'sidebar'    => 'fileSidebar',
            'navbar'     => 'fileNavbar',
            'login'      => 'fileLogin',
            'login_hero' => 'fileLoginHero',
            'avatar'     => 'fileAvatar',
        ];

        $prop = $propMap[$type] ?? null;
        if (!$prop || empty($this->{$prop})) {
            return;
        }

        $this->validate([
            $prop => 'image|max:2048|mimes:png,jpg,jpeg,webp,svg',
        ], [
            "{$prop}.image" => __('File harus berupa gambar (PNG, JPG, JPEG, WebP, SVG).'),
            "{$prop}.max"   => __('Ukuran file maksimal adalah 2MB.'),
        ]);

        BrandHelper::saveUploadedLogo($type, $this->{$prop});
        $this->{$prop} = null;

        session()->flash('success_' . $type, __('Logo berhasil diperbarui!'));
    }

    /**
     * Reset a specific logo back to .env / default.
     */
    public function resetLogo(string $type): void
    {
        BrandHelper::resetLogo($type);
        session()->flash('success_' . $type, __('Logo dikembalikan ke konfigurasi default (.env)!'));
    }

    /**
     * Reset all brand settings back to .env defaults.
     */
    public function resetAllToEnv(): void
    {
        $keys = [
            'brand_company_name',
            'brand_company_short_name',
            'brand_company_tagline',
            'brand_theme_primary',
            'brand_theme_dark',
            'brand_logo_sidebar',
            'brand_logo_navbar',
            'brand_logo_login',
            'brand_logo_login_hero',
            'brand_logo_avatar',
        ];

        foreach (['sidebar', 'navbar', 'login', 'login_hero', 'avatar'] as $t) {
            BrandHelper::resetLogo($t);
        }

        foreach ($keys as $k) {
            Setting::where('key', $k)->delete();
        }

        $this->loadSettings();
        session()->flash('success_details', __('Seluruh pengaturan brand dikembalikan ke default (.env)!'));
    }

    public function render()
    {
        return view('livewire.settings.brand-settings', [
            'activeSidebar'   => BrandHelper::logo('sidebar'),
            'activeNavbar'    => BrandHelper::logo('navbar'),
            'activeLogin'     => BrandHelper::logo('login'),
            'activeLoginHero' => BrandHelper::logo('login_hero'),
            'activeAvatar'    => BrandHelper::logo('avatar'),
            'isCustomSidebar' => BrandHelper::isCustom('sidebar'),
            'isCustomNavbar'  => BrandHelper::isCustom('navbar'),
            'isCustomLogin'   => BrandHelper::isCustom('login'),
            'isCustomHero'    => BrandHelper::isCustom('login_hero'),
            'isCustomAvatar'  => BrandHelper::isCustom('avatar'),
        ])->layout('layouts.contentNavbarLayout');
    }
}
