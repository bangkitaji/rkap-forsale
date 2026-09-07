<?php

namespace App\Helpers;

use App\Models\Setting;
use Illuminate\Support\Facades\File;

class BrandHelper
{
    /**
     * Get logo asset URL (checks database Setting first, falls back to config/env).
     *
     * @param string $type ('sidebar', 'navbar', 'login', 'login_hero', 'avatar')
     * @return string
     */
    public static function logo(string $type = 'sidebar'): string
    {
        $raw = self::path($type);
        if (empty($raw)) {
            return '';
        }

        if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
            return $raw;
        }

        return asset($raw);
    }

    /**
     * Get the raw relative path or URL for a logo.
     *
     * @param string $type
     * @return string
     */
    public static function path(string $type = 'sidebar'): string
    {
        // 1. Check database setting
        try {
            $settingKey = 'brand_logo_' . $type;
            $fromDb = Setting::get($settingKey);
            if (!empty($fromDb)) {
                return $fromDb;
            }
        } catch (\Throwable $e) {
            // Fallback gracefully if database or table is unavailable
        }

        // 2. Check config / .env
        $configKey = match ($type) {
            'avatar'  => 'avatar_default',
            'favicon' => 'favicon',
            default   => 'logo_' . $type,
        };
        $fromConfig = config('rkap.' . $configKey);

        if (!empty($fromConfig)) {
            return $fromConfig;
        }

        // 3. Sensible defaults
        return match ($type) {
            'navbar'     => 'assets/img/brand/logo_navbar.png',
            'login'      => 'assets/img/brand/logo_login.png',
            'login_hero' => 'assets/img/brand/login_hero.png',
            'avatar'     => 'assets/img/brand/default_avatar.png',
            'favicon'    => 'assets/img/favicon/favicon.png',
            default      => 'assets/img/brand/logo_sidebar.png',
        };
    }

    /**
     * Check if a custom logo is currently set in the database (overriding .env).
     *
     * @param string $type
     * @return bool
     */
    public static function isCustom(string $type = 'sidebar'): bool
    {
        try {
            return !empty(Setting::get('brand_logo_' . $type));
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Get configured company name.
     */
    public static function companyName(): string
    {
        try {
            $fromDb = Setting::get('brand_company_name');
            if (!empty($fromDb)) {
                return $fromDb;
            }
        } catch (\Throwable) {}

        return (string) config('rkap.company_name', 'PT Perusahaan');
    }

    /**
     * Get configured company short name.
     */
    public static function companyShortName(): string
    {
        try {
            $fromDb = Setting::get('brand_company_short_name');
            if (!empty($fromDb)) {
                return $fromDb;
            }
        } catch (\Throwable) {}

        return (string) config('rkap.company_short_name', 'Company');
    }

    /**
     * Get configured company tagline.
     */
    public static function companyTagline(): string
    {
        try {
            $fromDb = Setting::get('brand_company_tagline');
            if (!empty($fromDb)) {
                return $fromDb;
            }
        } catch (\Throwable) {}

        return (string) config('rkap.company_tagline', 'Sistem Rencana Kerja & Anggaran Perusahaan');
    }

    /**
     * Get primary theme color hex.
     */
    public static function themePrimary(): string
    {
        try {
            $fromDb = Setting::get('brand_theme_primary');
            if (!empty($fromDb)) {
                return $fromDb;
            }
        } catch (\Throwable) {}

        return (string) config('rkap.theme_primary', '#960b10');
    }

    /**
     * Get dark / sidebar theme color hex.
     */
    public static function themeDark(): string
    {
        try {
            $fromDb = Setting::get('brand_theme_dark');
            if (!empty($fromDb)) {
                return $fromDb;
            }
        } catch (\Throwable) {}

        return (string) config('rkap.theme_dark', '#1a1f5e');
    }

    /**
     * Get accent theme color hex.
     */
    public static function themeAccent(): string
    {
        try {
            $fromDb = Setting::get('brand_theme_accent');
            if (!empty($fromDb)) {
                return $fromDb;
            }
        } catch (\Throwable) {}

        return (string) config('rkap.theme_accent', '#b91c1c');
    }


    /**
     * Save an uploaded logo file and persist to Setting table.
     *
     * @param string $type ('sidebar', 'navbar', 'login', 'login_hero', 'avatar')
     * @param \Illuminate\Http\UploadedFile|\Livewire\Features\SupportFileUploads\TemporaryUploadedFile $file
     * @return string Relative asset path
     */
    public static function saveUploadedLogo(string $type, $file): string
    {
        $destDir = public_path('assets/img/brand/uploaded');
        if (!File::isDirectory($destDir)) {
            File::makeDirectory($destDir, 0755, true, true);
        }

        // Generate clean unique filename
        $extension = $file->getClientOriginalExtension() ?: 'png';
        $filename = 'logo_' . $type . '_' . time() . '.' . $extension;
        $targetPath = $destDir . DIRECTORY_SEPARATOR . $filename;

        // Use copy instead of move to support Livewire testing disks and cross-volume environments
        File::copy($file->getRealPath(), $targetPath);

        $relativePath = 'assets/img/brand/uploaded/' . $filename;

        // Clean up old uploaded file if exists
        $oldPath = Setting::get('brand_logo_' . $type);
        if (!empty($oldPath) && str_starts_with($oldPath, 'assets/img/brand/uploaded/')) {
            $oldFull = public_path($oldPath);
            if (File::exists($oldFull) && $oldFull !== $targetPath) {
                File::delete($oldFull);
            }
        }

        // Store into database setting
        Setting::set('brand_logo_' . $type, $relativePath);

        return $relativePath;
    }

    /**
     * Reset a logo back to default (.env / config).
     */
    public static function resetLogo(string $type): void
    {
        $oldPath = Setting::get('brand_logo_' . $type);
        if (!empty($oldPath) && str_starts_with($oldPath, 'assets/img/brand/uploaded/')) {
            $oldFull = public_path($oldPath);
            if (File::exists($oldFull)) {
                File::delete($oldFull);
            }
        }

        Setting::where('key', 'brand_logo_' . $type)->delete();
    }
}
