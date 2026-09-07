<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Database\Seeders\DemoSeeder;

class SetupDemo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rkap:demo {--fresh : Jalankan migrate:fresh sebelum mengisi dataset demo}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup lingkungan demo siap presentasi (akun multi-role, simulasi approval, & realisasi visual)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('╔═══════════════════════════════════════════════════════════╗');
        $this->info('║          RKAP - SETUP ENVIRONMENT DEMO SHOWCASE           ║');
        $this->info('╚═══════════════════════════════════════════════════════════╝');
        $this->line('Perintah ini akan menyiapkan data demo siap presentasi:');
        $this->line('1. Akun login per peran alur bisnis (Admin, Biro, Dept, Direksi, Verifikator, Dirut)');
        $this->line('2. Pengajuan RKAP dengan status multi-tahap (Draft, Submitted, Review, Approved)');
        $this->line('3. Data alokasi bulanan & serapan realisasi untuk menghidupkan grafik visual');
        $this->line('4. Simulasi modul pergeseran anggaran (Budget Transfer)');
        $this->newLine();

        if ($this->option('fresh')) {
            if (!$this->confirm('PERINGATAN: Opsi --fresh akan MENGHAPUS SEMUA DATA dalam database saat ini. Lanjutkan?', true)) {
                $this->warn('Setup demo dibatalkan.');
                return 0;
            }

            $this->info('Menjalankan migrasi database baru (migrate:fresh)...');
            $this->call('migrate:fresh', ['--force' => true]);
        } else {
            $this->info('Memeriksa migrasi database (migrate)...');
            $this->call('migrate', ['--force' => true]);
        }

        // Set config flag dynamically
        config(['rkap.seed_demo_data' => true]);

        // Run DemoSeeder
        $this->info('Mempopulasikan dataset demo lengkap (DemoSeeder)...');
        $this->call('db:seed', [
            '--class' => DemoSeeder::class,
            '--force' => true,
        ]);

        // Clear application caches
        $this->info('Membersihkan cache konfigurasi & aplikasi...');
        $this->call('cache:clear');
        $this->call('config:clear');
        $this->call('view:clear');

        $this->newLine();
        $this->info('✔ Lingkungan Demo RKAP Berhasil Disiapkan!');
        $this->newLine();

        $demoPassword = config('rkap.demo_password', 'Demo123!');
        $orgLabels = config('rkap.org_labels', ['level_1' => 'Direktorat', 'level_2' => 'Departemen', 'level_3' => 'Biro']);

        // Display Demo Accounts Table (dynamically from DB)
        $this->comment('── 1. DAFTAR AKUN DEMO PER PERAN ─────────────────────────────');
        $demoUsers = \App\Models\User::where('email', 'like', 'demo.%@rkap.com')
            ->with('roles')
            ->orderByRaw("CASE
                WHEN email = 'demo.admin@rkap.com' THEN 1
                WHEN email = 'demo.biro@rkap.com' THEN 2
                WHEN email = 'demo.dept@rkap.com' THEN 3
                WHEN email = 'demo.direksi@rkap.com' THEN 4
                WHEN email = 'demo.verifikator@rkap.com' THEN 5
                WHEN email = 'demo.dirut@rkap.com' THEN 6
                WHEN email = 'demo.staff@rkap.com' THEN 7
                ELSE 8 END")
            ->get();

        $roleScenarios = [
            'admin' => 'Setting brand, user management, periode, master data',
            'kepala_biro' => 'Input program kerja, rincian biaya COA, submit RKAP',
            'kepala_departemen' => 'Review pengajuan bawahan, approval, catatan revisi',
            'direksi' => 'Approval tingkat pimpinan untuk pengajuan dari unit',
            'verifikator' => 'Verifikasi akhir anggaran, upload realisasi, analitik',
            'direktur_utama' => 'Persetujuan akhir RKAP tingkat perusahaan',
            'user' => 'Akses staf untuk pengisian & pemantauan pagu anggaran',
        ];

        $accountRows = $demoUsers->map(function ($u) use ($demoPassword, $roleScenarios) {
            $roleName = $u->roles->first()?->name ?? '-';
            return [
                $u->email,
                $demoPassword,
                $roleName,
                $u->position ?? '-',
                $roleScenarios[$roleName] ?? '-',
            ];
        })->toArray();

        $this->table(
            ['Email Akun Demo', 'Password', 'Peran (Role)', 'Jabatan / Posisi', 'Skenario Presentasi'],
            $accountRows
        );

        $this->newLine();

        // Display Seeded RKAP Submissions Table (dynamically from DB)
        $this->comment('── 2. DATA PENGAJUAN RKAP MULTI-STATUS ───────────────────────');

        $statusScenarios = [
            'approved' => 'Dashboard grafik serapan, realisasi bulanan, & transfer budget',
            'final_review' => 'Login demo.verifikator: Lakukan verifikasi anggaran',
            'dir_review' => 'Login demo.direksi: Lakukan approval tingkat pimpinan',
            'submitted' => 'Login demo.dept: Review & approve pengajuan bawahan',
            'draft' => 'Login demo.biro: Demonstrasi pengisian dan pengeditan draf',
        ];

        $submissions = \App\Models\RkapSubmission::with(['bureau', 'workPlans'])
            ->whereHas('bureau')
            ->orderByRaw("CASE status
                WHEN 'approved' THEN 1
                WHEN 'final_review' THEN 2
                WHEN 'dir_review' THEN 3
                WHEN 'submitted' THEN 4
                WHEN 'draft' THEN 5
                ELSE 6 END")
            ->take(5)
            ->get();

        $subRows = $submissions->map(function ($s) use ($orgLabels, $statusScenarios) {
            $bureauLabel = $s->bureau->code . ' (' . $s->bureau->name . ')';
            $programName = $s->workPlans->first()?->program_name ?? '-';
            $budget = 'Rp ' . number_format($s->total_budget, 0, ',', '.');
            $status = ucwords(str_replace('_', ' ', $s->status));
            return [$bureauLabel, $programName, $budget, $status, $statusScenarios[$s->status] ?? '-'];
        })->toArray();

        $this->table(
            ["{$orgLabels['level_3']} Pengusul", 'Program Kerja Utama', 'Total Pagu', 'Status Alur', 'Fitur yang Siap Didemokan'],
            $subRows
        );

        $this->newLine();
        $this->info('Tips: Panduan alur presentasi lengkap tersedia di: docs/DEMO_GUIDE.md');
        $this->line('Aplikasi siap diakses melalui browser. Selamat mempresentasikan RKAP!');
        $this->newLine();

        return 0;
    }
}
