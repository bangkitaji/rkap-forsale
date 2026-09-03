<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class SetupTenant extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rkap:setup {--force : Force execution without extra confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup and configure RKAP for a new company / tenant (white-label)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('===========================================================');
        $this->info('       RKAP - WIZARD DEPLOYMENT PERUSAHAAN (WHITE-LABEL)    ');
        $this->info('===========================================================');
        $this->line('Wizard ini membantu mengkonfigurasi identitas perusahaan, branding,');
        $this->line('tema warna, akun administrator, serta inisialisasi database.');
        $this->newLine();

        $envPath = base_path('.env');
        if (!File::exists($envPath)) {
            if (File::exists(base_path('.env.example'))) {
                File::copy(base_path('.env.example'), $envPath);
                $this->info('Berhasil membuat file .env dari .env.example.');
                Artisan::call('key:generate');
            } else {
                $this->error('File .env dan .env.example tidak ditemukan!');
                return 1;
            }
        }

        // ── 1. Interaktif: Data Perusahaan ──
        $companyName = $this->ask('1. Nama Lengkap Perusahaan', config('rkap.company_name', 'PT Perusahaan Anda'));
        $shortName   = $this->ask('2. Nama Singkatan / Akronim Perusahaan', config('rkap.company_short_name', 'Company'));
        $companyUrl  = $this->ask('3. Website Perusahaan', config('rkap.company_url', 'https://example.com'));
        $tagline     = $this->ask('4. Tagline Aplikasi', config('rkap.company_tagline', 'Sistem Rencana Kerja & Anggaran Perusahaan'));
        
        $suggestedDomain = parse_url($companyUrl, PHP_URL_HOST) ?? 'example.com';
        $suggestedDomain = preg_replace('/^www\./', '', $suggestedDomain);
        $domain = $this->ask('5. Domain Email Perusahaan (misal: pt-abc.co.id)', $suggestedDomain);

        // ── 2. Interaktif: Akun Administrator ──
        $adminEmail = $this->ask('6. Email Akun Super Administrator', "admin@{$domain}");
        $adminPassword = $this->secret('7. Password Akun Administrator (default: P@ssw0rd!)') ?: 'P@ssw0rd!';

        // ── 3. Interaktif: Tema Warna ──
        $themePrimary = $this->ask('8. Warna Utama Brand (Hex, misal: #1e40af)', config('rkap.theme_primary', '#960b10'));
        $themeDark    = $this->ask('9. Warna Gelap / Sidebar (Hex, misal: #0f172a)', config('rkap.theme_dark', '#1a1f5e'));

        // ── 4. Interaktif: Bahasa Default & Sample Data ──
        $defaultLang = $this->choice('10. Bahasa Default Aplikasi', ['en' => 'English (en)', 'id' => 'Bahasa Indonesia (id)'], 'en');
        $seedSampleData = $this->confirm('11. Apakah Anda ingin mengimpor data sampel/demo (KCIC sample data)? (Pilih No/Tidak untuk instalasi perusahaan bersih)', false);

        // ── 5. Simpan Konfigurasi ke .env ──
        $this->newLine();
        $this->info('Menyimpan konfigurasi ke file .env...');

        $updates = [
            'APP_LOCALE'              => "\"{$defaultLang}\"",
            'APP_FALLBACK_LOCALE'     => "\"en\"",
            'RKAP_COMPANY_NAME'       => "\"{$companyName}\"",
            'RKAP_COMPANY_SHORT_NAME' => "\"{$shortName}\"",
            'RKAP_COMPANY_URL'        => "\"{$companyUrl}\"",
            'RKAP_COMPANY_TAGLINE'    => "\"{$tagline}\"",
            'RKAP_EMAIL_DOMAIN'       => "\"{$domain}\"",
            'RKAP_ADMIN_EMAIL'        => "\"{$adminEmail}\"",
            'RKAP_SEED_PASSWORD'      => "\"{$adminPassword}\"",
            'RKAP_THEME_PRIMARY'      => "\"{$themePrimary}\"",
            'RKAP_THEME_DARK'         => "\"{$themeDark}\"",
            'RKAP_SEED_SAMPLE_DATA'   => $seedSampleData ? 'true' : 'false',
        ];

        $this->updateEnvironmentFile($updates);

        // ── 6. Inisialisasi Direktori Brand ──
        $brandDir = public_path('assets/img/brand');
        if (!File::isDirectory($brandDir)) {
            File::makeDirectory($brandDir, 0755, true);
        }

        // Copy default templates if not exists
        $defaults = [
            'logo_sidebar.png'  => 'assets/img/kcic/logo_rkap.png',
            'logo_navbar.png'   => 'assets/img/kcic/logo_kcic.png',
            'logo_login.png'    => 'assets/img/kcic/logo_kbudgeting.png',
            'login_hero.png'    => 'assets/img/kcic/login_hero_train.png',
            'default_avatar.png'=> 'assets/img/avatars/1.png',
        ];

        foreach ($defaults as $target => $source) {
            $targetPath = $brandDir . DIRECTORY_SEPARATOR . $target;
            $sourcePath = public_path($source);
            if (!File::exists($targetPath) && File::exists($sourcePath)) {
                File::copy($sourcePath, $targetPath);
            }
        }

        $this->info('Aset default branding di direktori public/assets/img/brand siap.');

        // ── 7. Generate Excel Import Templates ──
        $this->info('Memeriksa template Excel untuk import Master Data...');
        try {
            Artisan::call('generate:import-templates');
            $this->info('Template import Excel (COA, WorkPlan, Activity) berhasil dibuat.');
        } catch (\Throwable $e) {
            $this->warn('Notice: ' . $e->getMessage());
        }

        // ── 8. Inisialisasi Database ──
        $this->newLine();
        if ($this->confirm('Apakah Anda ingin menjalankan database migrations & seed sekarang?', true)) {
            $fresh = $this->confirm('Gunakan migrate:fresh (HAPUS SEMUA TABEL & DATA LAMA)?', false);
            
            $this->info('Menjalankan migrasi database...');
            if ($fresh) {
                $this->call('migrate:fresh', ['--force' => true]);
            } else {
                $this->call('migrate', ['--force' => true]);
            }

            $this->info('Menjalankan seed database...');
            $this->call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
        }

        // Clear configuration cache
        Artisan::call('config:clear');
        Artisan::call('cache:clear');

        // ── 9. Ringkasan Selesai ──
        $this->newLine();
        $this->info('===========================================================');
        $this->info('             SETUP PERUSAHAAN BERHASIL DISELESAIKAN!        ');
        $this->info('===========================================================');
        $this->table(
            ['Parameter', 'Nilai'],
            [
                ['Nama Perusahaan', $companyName],
                ['Akronim', $shortName],
                ['Website', $companyUrl],
                ['Email Administrator', $adminEmail],
                ['Password Administrator', $adminPassword],
                ['Warna Primer', $themePrimary],
                ['Sample Data', $seedSampleData ? 'Ya (Demo)' : 'Tidak (Bersih)'],
                ['Direktori Logo', 'public/assets/img/brand/'],
            ]
        );

        $this->newLine();
        $this->info('Langkah Selanjutnya:');
        $this->line('1. Ganti logo di folder public/assets/img/brand/ sesuai panduan.');
        $this->line('2. Buka panduan master data: docs/MASTER_DATA_GUIDE.md');
        $this->line('3. Buka panduan deployment: docs/DEPLOYMENT_GUIDE.md');
        $this->line('4. Akses aplikasi di browser dan login dengan akun administrator.');

        return 0;
    }

    /**
     * Update environment file key-values.
     */
    protected function updateEnvironmentFile(array $data): void
    {
        $envPath = base_path('.env');
        $content = File::get($envPath);

        foreach ($data as $key => $value) {
            if (preg_match("/^{$key}=.*/m", $content)) {
                $content = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $content);
            } else {
                $content .= "\n{$key}={$value}";
            }
        }

        File::put($envPath, $content);
    }
}
