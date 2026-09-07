<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use App\Models\Directorate;
use App\Models\Department;
use App\Models\Bureau;
use App\Models\RkapPeriod;
use App\Models\RkapSubmission;
use App\Models\RkapWorkPlan;
use App\Models\RkapBudgetItem;
use App\Models\RkapBudgetItemMonthly;
use App\Models\RkapBudgetItemCashOut;
use App\Models\RkapBudgetItemRealization;
use App\Models\RkapBudgetItemProjection;
use App\Models\RkapApproval;
use App\Models\RkapComment;
use App\Models\BudgetTransfer;
use App\Models\BudgetTransferItem;
use App\Models\WorkPlan;
use App\Models\Activity;
use App\Models\Coa;
use App\Models\Satuan;
use App\Enums\SubmissionStatus;
use App\Enums\ApprovalAction;
use App\Enums\ApprovalRole;
use App\Enums\BudgetTransferStatus;
use App\Models\BalanceSheetOpeningBalance;

class DemoSeeder extends Seeder
{
    /**
     * Run the demo database seeds.
     *
     * This seeder is organization-agnostic: it dynamically discovers whatever
     * directorates, departments, and bureaus exist in the database and
     * assigns demo accounts and submissions accordingly. It works regardless
     * of the company's industry sector (transportation, manufacturing,
     * financial services, government, etc.).
     */
    public function run(): void
    {
        $this->command->info('====================================================');
        $this->command->info('        PREPARING DEMO SHOWCASE ENVIRONMENT         ');
        $this->command->info('====================================================');

        // 1. Ensure core dependencies (Roles, Permissions, Master Data)
        $this->ensureCorePrerequisites();

        // 2. Ensure Organization structure exists (any structure, not sector-specific)
        $this->ensureOrganizationStructure();

        // 3. Ensure Master Data (COA, WorkPlans, Activities)
        $this->ensureMasterData();

        // 4. Create/Update Active RKAP Period
        $period = $this->ensureActivePeriod();

        // 5. Create Demo Accounts (dynamically assigned to existing org)
        $users = $this->seedDemoUsers();

        // 6. Seed Multi-Status RKAP Submissions
        $this->seedDemoSubmissions($period, $users);

        // 7. Seed Annual Historical & Future Comparison Periods (2025 & 2027)
        $this->seedHistoricalComparisonData($users);

        // 8. Seed Balanced Opening Balances for Balance Sheet
        $this->seedBalanceSheetOpeningBalances($period, $users);

        $this->command->newLine();
        $this->command->info('Demo showcase dataset successfully populated!');
    }

    /**
     * Ensure roles, permissions, and units exist.
     */
    protected function ensureCorePrerequisites(): void
    {
        $this->command->line('1. Checking core permissions & units...');

        $this->call([
            RoleAndUserSeeder::class,
            SatuanSeeder::class,
        ]);
    }

    /**
     * Ensure at minimum a generic organization structure exists.
     * If the company has already set up their own org, we use that.
     * Otherwise we create a minimal generic structure for demo purposes.
     */
    protected function ensureOrganizationStructure(): void
    {
        $this->command->line('2. Checking organizational structure...');

        // If there is already an org structure (from RkapSeeder or manual setup), use it.
        if (Directorate::count() > 0 && Department::count() > 0 && Bureau::count() > 0) {
            $this->command->line('   → Existing organization structure detected. Using as-is.');
            return;
        }

        // Otherwise, create a generic minimal structure that applies to any company.
        $orgLabels = config('rkap.org_labels', [
            'level_1' => 'Direktorat',
            'level_2' => 'Departemen',
            'level_3' => 'Biro',
        ]);

        $this->command->line("   → No organization found. Creating generic demo structure...");

        // Generic directorates that exist in virtually every company
        $dirCEO = Directorate::firstOrCreate(
            ['code' => 'DIR-01'],
            ['name' => 'Direktur Utama / CEO', 'description' => "{$orgLabels['level_1']} Pimpinan Tertinggi", 'is_active' => true]
        );
        $dirFinance = Directorate::firstOrCreate(
            ['code' => 'DIR-02'],
            ['name' => 'Keuangan & Administrasi', 'description' => "{$orgLabels['level_1']} Keuangan, Akuntansi & Administrasi Umum", 'is_active' => true]
        );
        $dirOperations = Directorate::firstOrCreate(
            ['code' => 'DIR-03'],
            ['name' => 'Operasional & Produksi', 'description' => "{$orgLabels['level_1']} Operasional, Produksi & Layanan", 'is_active' => true]
        );
        $dirHR = Directorate::firstOrCreate(
            ['code' => 'DIR-04'],
            ['name' => 'SDM & Umum', 'description' => "{$orgLabels['level_1']} Sumber Daya Manusia & Urusan Umum", 'is_active' => true]
        );

        // Generic departments
        $deptFinBudget = Department::firstOrCreate(
            ['code' => 'DEP-FIN'],
            ['directorate_id' => $dirFinance->id, 'name' => 'Keuangan & Anggaran', 'is_verifier' => false, 'is_active' => true]
        );
        $deptAccounting = Department::firstOrCreate(
            ['code' => 'DEP-ACC'],
            ['directorate_id' => $dirFinance->id, 'name' => 'Akuntansi & Pelaporan', 'is_verifier' => true, 'is_active' => true]
        );
        $deptOperations = Department::firstOrCreate(
            ['code' => 'DEP-OPS'],
            ['directorate_id' => $dirOperations->id, 'name' => 'Operasional', 'is_verifier' => false, 'is_active' => true]
        );
        $deptHR = Department::firstOrCreate(
            ['code' => 'DEP-HRD'],
            ['directorate_id' => $dirHR->id, 'name' => 'SDM & Pengembangan', 'is_verifier' => false, 'is_active' => true]
        );
        $deptGA = Department::firstOrCreate(
            ['code' => 'DEP-GA'],
            ['directorate_id' => $dirCEO->id, 'name' => 'Umum & Sekretariat', 'is_verifier' => false, 'is_active' => true]
        );

        // Generic bureaus / sections
        Bureau::firstOrCreate(
            ['code' => 'BIR-FIN-01'],
            ['department_id' => $deptFinBudget->id, 'name' => 'Perencanaan Anggaran', 'is_active' => true]
        );
        Bureau::firstOrCreate(
            ['code' => 'BIR-FIN-02'],
            ['department_id' => $deptFinBudget->id, 'name' => 'Perbendaharaan', 'is_active' => true]
        );
        Bureau::firstOrCreate(
            ['code' => 'BIR-ACC-01'],
            ['department_id' => $deptAccounting->id, 'name' => 'Verifikasi & Analisis', 'is_active' => true]
        );
        Bureau::firstOrCreate(
            ['code' => 'BIR-OPS-01'],
            ['department_id' => $deptOperations->id, 'name' => 'Operasional Lapangan', 'is_active' => true]
        );
        Bureau::firstOrCreate(
            ['code' => 'BIR-HRD-01'],
            ['department_id' => $deptHR->id, 'name' => 'Pelatihan & Pengembangan', 'is_active' => true]
        );
        Bureau::firstOrCreate(
            ['code' => 'BIR-GA-01'],
            ['department_id' => $deptGA->id, 'name' => 'Hubungan Masyarakat & Protokol', 'is_active' => true]
        );
    }

    /**
     * Ensure Master Data (COA, Work Plans, Activities) exists.
     */
    protected function ensureMasterData(): void
    {
        $this->command->line('3. Checking master data (COA & Activities)...');

        if (Coa::count() === 0) {
            config(['rkap.seed_sample_data' => true]);
            $this->call([
                CashflowGroupSeeder::class,
                DifferenceGroupSeeder::class,
                MasterDataSeeder::class,
                CoaCfTypeMappingSeeder::class,
                CoaProfitLossMappingSeeder::class,
                CashflowReportGroupMappingSeeder::class,
                CashFlowSeeder::class,
                CdsGroupSeeder::class,
            ]);
        }

        // Fallback generic work plans & activities if none exist
        if (WorkPlan::count() === 0) {
            $genericWorkPlans = [
                ['code' => 'WP-01', 'title' => 'Pengelolaan Sistem Informasi & Teknologi'],
                ['code' => 'WP-02', 'title' => 'Pengembangan Sumber Daya Manusia'],
                ['code' => 'WP-03', 'title' => 'Pengelolaan Keuangan & Akuntansi'],
                ['code' => 'WP-04', 'title' => 'Pengelolaan Operasional & Layanan'],
            ];
            $genericActivities = [
                ['code' => 'ACT-01-01', 'wp' => 'WP-01', 'title' => 'Pemeliharaan Infrastruktur & Lisensi Sistem'],
                ['code' => 'ACT-02-01', 'wp' => 'WP-02', 'title' => 'Pelatihan & Sertifikasi Kompetensi'],
                ['code' => 'ACT-03-01', 'wp' => 'WP-03', 'title' => 'Penyusunan Laporan Keuangan & RKAP'],
                ['code' => 'ACT-04-01', 'wp' => 'WP-04', 'title' => 'Operasional Rutin & Pemeliharaan Aset'],
            ];
            $wpMap = [];
            foreach ($genericWorkPlans as $wp) {
                $wpMap[$wp['code']] = WorkPlan::firstOrCreate(['code' => $wp['code']], ['title' => $wp['title']]);
            }
            foreach ($genericActivities as $act) {
                Activity::firstOrCreate(
                    ['code' => $act['code']],
                    ['work_plan_id' => $wpMap[$act['wp']]->id, 'title' => $act['title']]
                );
            }
        }

        // Fallback generic COAs if none exist
        if (Coa::count() === 0) {
            $genericCoas = [
                ['code' => '580101', 'title' => 'Beban Cloud Infrastructure & Server Hosting'],
                ['code' => '580102', 'title' => 'Beban Lisensi Database & API Platform'],
                ['code' => '660101', 'title' => 'Beban Pelatihan & Sertifikasi Karyawan'],
                ['code' => '680103', 'title' => 'Beban Merchandise & Publikasi Branding'],
                ['code' => '650102', 'title' => 'Beban Jasa Konsultan Hukum & Notaris'],
                ['code' => '610101', 'title' => 'Beban Alat Tulis Kantor & Keperluan Operasional'],
            ];
            foreach ($genericCoas as $coa) {
                Coa::firstOrCreate(['code' => $coa['code']], ['title' => $coa['title']]);
            }
        }
    }

    /**
     * Ensure active RKAP period for current year exists.
     */
    protected function ensureActivePeriod(): RkapPeriod
    {
        $this->command->line('4. Initializing active RKAP period...');
        $companyName = config('rkap.company_short_name', 'Perusahaan');
        $year = (int) date('Y');

        return RkapPeriod::updateOrCreate(
            ['year' => $year],
            [
                'title' => "RKAP {$companyName} Tahun $year",
                'description' => "Periode Penyusunan RKAP Tahun $year (Setup Demo)",
                'status' => 'open',
                'submission_start' => now()->startOfYear()->toDateString(),
                'submission_end' => now()->endOfYear()->toDateString(),
            ]
        );
    }

    /**
     * Dynamically resolve org units from the database for demo user placement.
     *
     * @return array{directorates: \Illuminate\Support\Collection, departments: \Illuminate\Support\Collection, bureaus: \Illuminate\Support\Collection}
     */
    protected function resolveOrganization(): array
    {
        $directorates = Directorate::where('is_active', true)->get();
        $departments = Department::where('is_active', true)->with('directorate')->get();
        $bureaus = Bureau::where('is_active', true)->with('department.directorate')->get();

        return compact('directorates', 'departments', 'bureaus');
    }

    /**
     * Seed predefined, easy-to-remember demo accounts.
     * Accounts are dynamically assigned to whatever org structure exists.
     *
     * @return array<string, User>
     */
    protected function seedDemoUsers(): array
    {
        $this->command->line('5. Seeding demo multi-role users...');

        $password = config('rkap.demo_password', 'Demo123!');
        $hashedPassword = Hash::make($password);
        $orgLabels = config('rkap.org_labels', [
            'level_1' => 'Direktorat',
            'level_2' => 'Departemen',
            'level_3' => 'Biro',
        ]);

        $org = $this->resolveOrganization();

        // Pick the first directorate for CEO-level roles
        $dirFirst = $org['directorates']->first();
        // Try to find a second directorate for direksi, else fallback to first
        $dirSecond = $org['directorates']->get(1) ?? $dirFirst;

        // Pick departments: prefer one under dirSecond, else any
        $deptUnderSecond = $org['departments']->where('directorate_id', $dirSecond?->id)->first();
        $deptAny = $deptUnderSecond ?? $org['departments']->first();
        // Try to find a verifier department for verifikator
        $deptVerifier = $org['departments']->where('is_verifier', true)->first() ?? $org['departments']->get(1) ?? $deptAny;

        // Pick bureaus
        $bureauForBiro = $org['bureaus']->where('department_id', $deptAny?->id)->first() ?? $org['bureaus']->first();
        $bureauForVerifikator = $org['bureaus']->where('department_id', $deptVerifier?->id)->first() ?? $org['bureaus']->get(1) ?? $bureauForBiro;

        $accounts = [
            'admin' => [
                'email' => 'demo.admin@rkap.com',
                'name' => 'Demo Administrator',
                'role' => 'admin',
                'position' => 'Super Administrator Sistem',
                'directorate_id' => $dirFirst?->id,
                'department_id' => null,
                'bureau_id' => null,
            ],
            'dirut' => [
                'email' => 'demo.dirut@rkap.com',
                'name' => 'Demo Pimpinan Utama',
                'role' => 'direktur_utama',
                'position' => config('rkap.role_labels.direktur_utama', 'Direktur Utama / Pimpinan'),
                'directorate_id' => $dirFirst?->id,
                'department_id' => null,
                'bureau_id' => null,
            ],
            'direksi' => [
                'email' => 'demo.direksi@rkap.com',
                'name' => 'Demo ' . config('rkap.role_labels.direksi', 'Direksi'),
                'role' => 'direksi',
                'position' => config('rkap.role_labels.direksi', 'Direksi') . ' — ' . ($dirSecond?->name ?? $orgLabels['level_1']),
                'directorate_id' => $dirSecond?->id,
                'department_id' => null,
                'bureau_id' => null,
            ],
            'dept' => [
                'email' => 'demo.dept@rkap.com',
                'name' => 'Demo ' . config('rkap.role_labels.kepala_departemen', 'Kepala Departemen'),
                'role' => 'kepala_departemen',
                'position' => config('rkap.role_labels.kepala_departemen', 'Kepala Departemen') . ' — ' . ($deptAny?->name ?? $orgLabels['level_2']),
                'directorate_id' => $deptAny?->directorate_id,
                'department_id' => $deptAny?->id,
                'bureau_id' => null,
            ],
            'biro' => [
                'email' => 'demo.biro@rkap.com',
                'name' => 'Demo ' . config('rkap.role_labels.kepala_biro', 'Kepala Biro'),
                'role' => 'kepala_biro',
                'position' => config('rkap.role_labels.kepala_biro', 'Kepala Biro') . ' — ' . ($bureauForBiro?->name ?? $orgLabels['level_3']),
                'directorate_id' => $bureauForBiro?->department?->directorate_id,
                'department_id' => $bureauForBiro?->department_id,
                'bureau_id' => $bureauForBiro?->id,
            ],
            'verifikator' => [
                'email' => 'demo.verifikator@rkap.com',
                'name' => 'Demo ' . config('rkap.role_labels.verifikator', 'Verifikator'),
                'role' => 'verifikator',
                'position' => config('rkap.role_labels.verifikator', 'Verifikator Anggaran') . ' — ' . ($deptVerifier?->name ?? $orgLabels['level_2']),
                'directorate_id' => $deptVerifier?->directorate_id,
                'department_id' => $deptVerifier?->id,
                'bureau_id' => $bureauForVerifikator?->id,
            ],
            'staff' => [
                'email' => 'demo.staff@rkap.com',
                'name' => 'Demo Staf Anggaran',
                'role' => 'user',
                'position' => 'Staf Perencanaan Anggaran — ' . ($bureauForBiro?->name ?? $orgLabels['level_3']),
                'directorate_id' => $bureauForBiro?->department?->directorate_id,
                'department_id' => $bureauForBiro?->department_id,
                'bureau_id' => $bureauForBiro?->id,
            ],
        ];

        $users = [];
        foreach ($accounts as $key => $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => $hashedPassword,
                    'directorate_id' => $data['directorate_id'],
                    'department_id' => $data['department_id'],
                    'bureau_id' => $data['bureau_id'],
                    'position' => $data['position'],
                    'must_change_password' => false,
                ]
            );

            $role = Role::firstOrCreate(['name' => $data['role']]);
            $user->syncRoles([$role]);
            $users[$key] = $user;
        }

        return $users;
    }

    /**
     * Pick up to N distinct bureaus from the database for demo submissions.
     * Returns array of Bureau models.
     *
     * @return Bureau[]
     */
    protected function pickDemoBureaus(int $count = 5): array
    {
        return Bureau::where('is_active', true)
            ->with('department.directorate')
            ->take($count)
            ->get()
            ->all();
    }

    /**
     * Resolve a COA by preferred code, or fall back to any COA matching report group type & code.
     */
    protected function resolveCoa(string $preferredCode, string $reportGroupType = 'PL', ?string $reportGroupCode = null): Coa
    {
        $coa = Coa::where('code', $preferredCode)->first();
        if ($coa) {
            return $coa;
        }

        $query = Coa::whereHas('coaGroup.reportGroup', function ($q) use ($reportGroupType, $reportGroupCode) {
            $q->where('type', $reportGroupType);
            if ($reportGroupCode) {
                $q->where('code', $reportGroupCode);
            }
        });

        return $query->first() ?? Coa::first();
    }

    /**
     * Seed 5 realistic submissions with different statuses.
     * Bureau assignment is dynamic — uses whatever bureaus exist.
     */
    protected function seedDemoSubmissions(RkapPeriod $period, array $users): void
    {
        $this->command->line('6. Seeding multi-status RKAP submissions & real transactions...');

        $bureaus = $this->pickDemoBureaus(5);
        if (empty($bureaus)) {
            $this->command->warn('   → No active bureaus found. Skipping submission seeding.');
            return;
        }

        // Grab available Work Plans & Activities
        $wpList = WorkPlan::with('activities')->take(5)->get();
        $wp1 = $wpList->get(0) ?? WorkPlan::first();
        $wp2 = $wpList->get(1) ?? $wp1;
        $act1 = $wp1?->activities?->first() ?? Activity::first();
        $act2 = $wp2?->activities?->first() ?? $act1;

        // Generic submission scenarios with accurate corporate P&L and Capex COA mapping
        $scenarios = [
            [
                'status' => SubmissionStatus::Approved,
                'notes' => 'RKAP telah disetujui penuh dan aktif berjalan sepanjang tahun.',
                'programs' => [
                    [
                        'code' => 'PRG-DEMO-01',
                        'name' => 'Program Layanan Komersial & Pendapatan Kontrak SLA',
                        'desc' => 'Penyediaan kontrak layanan SLA, pemeliharaan sistem terkelola, dan konsultansi klien.',
                        'target' => 'Kontrak SLA aktif 12 bulan dengan 99.9% uptime',
                        'unit' => 'Bulan',
                        'qty' => 12,
                        'items' => [
                            [
                                'desc' => 'Pendapatan Kontrak Layanan SLA & Pemeliharaan Sistem Klien',
                                'unit' => 'Bulan',
                                'qty' => 12,
                                'price' => 35000000,
                                'flow_direction' => 'in',
                                'coa_code' => '410201',
                                'rg_type' => 'PL',
                                'rg_code' => 'PL0001',
                            ],
                            [
                                'desc' => 'Pendapatan Jasa Konsultansi & Layanan Profesional Klien',
                                'unit' => 'Paket',
                                'qty' => 4,
                                'price' => 45000000,
                                'flow_direction' => 'in',
                                'coa_code' => '410102',
                                'rg_type' => 'PL',
                                'rg_code' => 'PL0001',
                            ],
                        ],
                    ],
                    [
                        'code' => 'PRG-DEMO-02',
                        'name' => 'Operasional & Pemeliharaan Sistem Informasi',
                        'desc' => 'Langganan infrastruktur cloud, server database terkelola, dan dukungan teknis.',
                        'target' => 'Sistem beroperasi 99.9% uptime sepanjang tahun',
                        'unit' => 'Bulan',
                        'qty' => 12,
                        'items' => [
                            [
                                'desc' => 'Langganan Cloud Infrastructure & Database Server AWS',
                                'unit' => 'Bulan',
                                'qty' => 12,
                                'price' => 15000000,
                                'flow_direction' => 'out',
                                'coa_code' => '580101',
                                'rg_type' => 'PL',
                                'rg_code' => 'PL0002',
                            ],
                            [
                                'desc' => 'Jasa Pemeliharaan & Dukungan Teknis Aplikasi',
                                'unit' => 'Bulan',
                                'qty' => 12,
                                'price' => 10000000,
                                'flow_direction' => 'out',
                                'coa_code' => '670103',
                                'rg_type' => 'PL',
                                'rg_code' => 'PL0003',
                            ],
                        ],
                    ],
                    [
                        'code' => 'PRG-DEMO-03',
                        'name' => 'Bimbingan Teknis & Workshop Penyusunan Anggaran',
                        'desc' => 'Sosialisasi regulasi dan standar penyusunan RKAP untuk seluruh unit kerja.',
                        'target' => '100% unit kerja memahami standar input',
                        'unit' => 'Paket',
                        'qty' => 1,
                        'items' => [
                            [
                                'desc' => 'Penyelenggaraan Workshop & Materi Panduan Anggaran',
                                'unit' => 'Paket',
                                'qty' => 1,
                                'price' => 50000000,
                                'flow_direction' => 'out',
                                'coa_code' => '660101',
                                'rg_type' => 'PL',
                                'rg_code' => 'PL0003',
                            ],
                        ],
                    ],
                    [
                        'code' => 'PRG-DEMO-04',
                        'name' => 'Pengadaan Infrastruktur & Server Baru (Capex)',
                        'desc' => 'Belanja modal pengadaan server fisik dan perangkat keras jaringan kantor.',
                        'target' => 'Server beroperasi penuh di data center kantor',
                        'unit' => 'Paket',
                        'qty' => 1,
                        'items' => [
                            [
                                'desc' => 'Pengadaan Server Komputasi & Perangkat Jaringan Fisik',
                                'unit' => 'Paket',
                                'qty' => 1,
                                'price' => 100000000,
                                'flow_direction' => 'out',
                                'coa_code' => '122101',
                                'rg_type' => 'BS',
                                'rg_code' => 'BS0002',
                            ],
                        ],
                    ],
                ],
                'withRealization' => true,
                'withTransfer' => true,
            ],
            [
                'status' => SubmissionStatus::FinalReview,
                'notes' => 'Pengajuan pengadaan perangkat teknologi informasi (Menunggu verifikasi akhir).',
                'programs' => [
                    [
                        'code' => 'PRG-DEMO-05',
                        'name' => 'Pengadaan & Penguatan Infrastruktur Keamanan Siber',
                        'desc' => 'Implementasi perangkat keras jaringan dan lisensi keamanan siber.',
                        'target' => 'Infrastruktur TI terbaru terpasang di seluruh lokasi',
                        'unit' => 'Paket',
                        'qty' => 1,
                        'items' => [
                            [
                                'desc' => 'Lisensi Perangkat Jaringan & Keamanan Siber',
                                'unit' => 'Paket',
                                'qty' => 1,
                                'price' => 175000000,
                                'flow_direction' => 'out',
                                'coa_code' => '670104',
                                'rg_type' => 'PL',
                                'rg_code' => 'PL0003',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'status' => SubmissionStatus::DirReview,
                'notes' => 'Program kehumasan & publikasi korporat (Menunggu persetujuan pimpinan).',
                'programs' => [
                    [
                        'code' => 'PRG-DEMO-06',
                        'name' => 'Program Komunikasi Korporat & Hubungan Masyarakat',
                        'desc' => 'Publikasi capaian perusahaan di media cetak dan elektronik.',
                        'target' => '4 kegiatan publikasi per tahun',
                        'unit' => 'Kegiatan',
                        'qty' => 4,
                        'items' => [
                            [
                                'desc' => 'Biaya Publikasi Media Digital & Kegiatan Kehumasan',
                                'unit' => 'Kegiatan',
                                'qty' => 4,
                                'price' => 25000000,
                                'flow_direction' => 'out',
                                'coa_code' => '680101',
                                'rg_type' => 'PL',
                                'rg_code' => 'PL0003',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'status' => SubmissionStatus::Submitted,
                'notes' => 'Program pelatihan & pengembangan kompetensi SDM (Menunggu review atasan).',
                'programs' => [
                    [
                        'code' => 'PRG-DEMO-07',
                        'name' => 'Pelatihan Kompetensi & Sertifikasi Profesional Karyawan',
                        'desc' => 'Peningkatan kapasitas manajerial dan sertifikasi teknis karyawan.',
                        'target' => '10 karyawan bersertifikat',
                        'unit' => 'Orang',
                        'qty' => 10,
                        'items' => [
                            [
                                'desc' => 'Biaya Pelatihan & Ujian Sertifikasi Profesional Karyawan',
                                'unit' => 'Orang',
                                'qty' => 10,
                                'price' => 8500000,
                                'flow_direction' => 'out',
                                'coa_code' => '660101',
                                'rg_type' => 'PL',
                                'rg_code' => 'PL0003',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'status' => SubmissionStatus::Draft,
                'notes' => 'Draf pengajuan anggaran baru (Dapat diedit langsung saat demo).',
                'programs' => [
                    [
                        'code' => 'PRG-DEMO-08',
                        'name' => 'Studi Kelayakan Otomasi Pelaporan Keuangan',
                        'desc' => 'Kajian integrasi sistem pelaporan keuangan dan anggaran otomatis.',
                        'target' => 'Dokumen rekomendasi arsitektur sistem',
                        'unit' => 'Dokumen',
                        'qty' => 1,
                        'items' => [
                            [
                                'desc' => 'Jasa Tenaga Ahli & Konsultansi Sistem Informasi',
                                'unit' => 'Bulan',
                                'qty' => 3,
                                'price' => 20000000,
                                'flow_direction' => 'out',
                                'coa_code' => '650103',
                                'rg_type' => 'PL',
                                'rg_code' => 'PL0003',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // Only seed as many submissions as we have distinct bureaus
        $scenarioCount = min(count($scenarios), count($bureaus));

        $firstSubmission = null;
        $firstWorkPlan = null;
        $firstBudgetItem = null;
        $firstBureau = null;

        for ($i = 0; $i < $scenarioCount; $i++) {
            $bureau = $bureaus[$i];
            $scenario = $scenarios[$i];
            $wp = ($i % 2 === 0) ? $wp1 : $wp2;
            $act = ($i % 2 === 0) ? $act1 : $act2;

            $submission = RkapSubmission::updateOrCreate(
                ['rkap_period_id' => $period->id, 'bureau_id' => $bureau->id],
                [
                    'created_by' => $users['biro']->id,
                    'current_version' => 1,
                    'status' => $scenario['status']->value,
                    'total_budget' => 0,
                    'notes' => $scenario['notes'],
                ]
            );

            // Clean previous workplans for clean demo state
            $submission->workPlans()->delete();

            foreach ($scenario['programs'] as $progIdx => $prog) {
                $rwp = RkapWorkPlan::create([
                    'rkap_submission_id' => $submission->id,
                    'work_plan_id' => $wp?->id,
                    'activity_id' => $act?->id,
                    'program_code' => $prog['code'],
                    'program_name' => $prog['name'],
                    'description' => $prog['desc'],
                    'output_target' => $prog['target'],
                    'unit' => $prog['unit'],
                    'quantity' => $prog['qty'],
                    'sort_order' => $progIdx + 1,
                    'approval_status' => $scenario['status'] === SubmissionStatus::Approved ? 'approved' : 'pending',
                ]);

                foreach ($prog['items'] as $itemIdx => $itemData) {
                    $coaForItem = $this->resolveCoa(
                        $itemData['coa_code'] ?? '610101',
                        $itemData['rg_type'] ?? 'PL',
                        $itemData['rg_code'] ?? 'PL0003'
                    );
                    $totalPrice = $itemData['qty'] * $itemData['price'];

                    $budgetItem = RkapBudgetItem::create([
                        'rkap_work_plan_id' => $rwp->id,
                        'account_code' => $coaForItem->code,
                        'description' => $itemData['desc'],
                        'unit' => $itemData['unit'],
                        'quantity' => $itemData['qty'],
                        'unit_price' => $itemData['price'],
                        'total_price' => $totalPrice,
                        'projection' => $totalPrice,
                        'flow_direction' => $itemData['flow_direction'] ?? 'out',
                    ]);

                    // Generate monthly distributions (allocation, cash-out, projection & optional realization)
                    $this->seedMonthlyDistributions(
                        $budgetItem,
                        $period,
                        $users['verifikator'],
                        !empty($scenario['withRealization'])
                    );

                    // Store reference to an expense budget item for budget transfer showcase
                    if ($i === 0 && $progIdx === 1 && $itemIdx === 0) {
                        $firstSubmission = $submission;
                        $firstWorkPlan = $rwp;
                        $firstBudgetItem = $budgetItem;
                        $firstBureau = $bureau;
                    }
                }
            }

            // Recalculate submission total_budget cleanly
            $submission->recalculateTotalBudget();

            // Seed approval trail based on status progression
            $this->seedApprovalTrail($submission, $scenario['status'], $users);

            // Seed sample comment for FinalReview status
            if ($scenario['status'] === SubmissionStatus::FinalReview) {
                $submission->comments()->delete();
                RkapComment::create([
                    'rkap_submission_id' => $submission->id,
                    'user_id' => $users['verifikator']->id,
                    'version_number' => 1,
                    'content' => 'Dokumen pendukung dan perbandingan harga telah diterima. Sedang dalam tahap verifikasi kewajaran harga.',
                    'created_at' => now()->subDays(1),
                ]);
            }
        }

        // Seed budget transfer between first and second bureau
        if ($firstSubmission && $firstBudgetItem && count($bureaus) >= 2) {
            $this->seedSampleBudgetTransfer(
                $period, $firstBureau, $bureaus[1],
                $firstSubmission, $firstWorkPlan, $firstBudgetItem, $users
            );
        }
    }

    /**
     * Generate monthly distributions (allocation, cash-out, realization, projection) for a budget item.
     */
    protected function seedMonthlyDistributions(
        RkapBudgetItem $item,
        RkapPeriod $period,
        User $verifikator,
        bool $withRealization = true
    ): void {
        $isMonthlyRecurring = $item->quantity >= 12 && in_array(strtolower($item->unit), ['bulan', 'month']);

        if ($isMonthlyRecurring) {
            $monthlyAmount = round($item->total_price / 12, 2);
            for ($m = 1; $m <= 12; $m++) {
                RkapBudgetItemMonthly::create([
                    'rkap_budget_item_id' => $item->id, 'month' => $m, 'amount' => $monthlyAmount,
                ]);
                RkapBudgetItemCashOut::create([
                    'rkap_budget_item_id' => $item->id, 'month' => $m, 'amount' => $monthlyAmount,
                ]);
            }

            if ($withRealization) {
                // Realization data (Jan–Jun) with realistic variance
                $multipliers = [1 => 1.00, 2 => 0.98, 3 => 1.02, 4 => 0.95, 5 => 0.97, 6 => 1.00];
                $totalRealization = 0.0;
                for ($m = 1; $m <= 6; $m++) {
                    $realAmount = round($monthlyAmount * ($multipliers[$m] ?? 1.0), 2);
                    $totalRealization += $realAmount;
                    RkapBudgetItemRealization::create([
                        'rkap_budget_item_id' => $item->id,
                        'rkap_period_id' => $period->id,
                        'month' => $m,
                        'amount' => $realAmount,
                        'uploaded_by' => $verifikator->id,
                        'uploaded_at' => now()->subMonths(6 - $m),
                    ]);

                    // Projections for closed months match realization
                    RkapBudgetItemProjection::create([
                        'rkap_budget_item_id' => $item->id,
                        'rkap_period_id' => $period->id,
                        'month' => $m,
                        'amount' => $realAmount,
                        'inputted_by' => $verifikator->id,
                    ]);
                }

                // Projections for open months (Jul-Dec) match planned monthly amount
                for ($m = 7; $m <= 12; $m++) {
                    RkapBudgetItemProjection::create([
                        'rkap_budget_item_id' => $item->id,
                        'rkap_period_id' => $period->id,
                        'month' => $m,
                        'amount' => $monthlyAmount,
                        'inputted_by' => $verifikator->id,
                    ]);
                }

                $totalProjection = $totalRealization + ($monthlyAmount * 6);
                $item->update(['projection' => $totalProjection]);
            } else {
                // For unapproved / pending items, projection matches the planned monthly amount
                for ($m = 1; $m <= 12; $m++) {
                    RkapBudgetItemProjection::create([
                        'rkap_budget_item_id' => $item->id,
                        'rkap_period_id' => $period->id,
                        'month' => $m,
                        'amount' => $monthlyAmount,
                        'inputted_by' => $verifikator->id,
                    ]);
                }
                $item->update(['projection' => $item->total_price]);
            }
        } else {
            // Lump-sum: allocate in month 4 (Q2)
            $allocMonth = 4;
            RkapBudgetItemMonthly::create([
                'rkap_budget_item_id' => $item->id, 'month' => $allocMonth, 'amount' => $item->total_price,
            ]);
            RkapBudgetItemCashOut::create([
                'rkap_budget_item_id' => $item->id, 'month' => $allocMonth, 'amount' => $item->total_price,
            ]);

            if ($withRealization) {
                $realAmount = round($item->total_price * 0.975, 2);
                RkapBudgetItemRealization::create([
                    'rkap_budget_item_id' => $item->id,
                    'rkap_period_id' => $period->id,
                    'month' => $allocMonth,
                    'amount' => $realAmount,
                    'uploaded_by' => $verifikator->id,
                    'uploaded_at' => now()->subMonths(2),
                ]);

                for ($m = 1; $m <= 12; $m++) {
                    RkapBudgetItemProjection::create([
                        'rkap_budget_item_id' => $item->id,
                        'rkap_period_id' => $period->id,
                        'month' => $m,
                        'amount' => ($m === $allocMonth) ? $realAmount : 0.0,
                        'inputted_by' => $verifikator->id,
                    ]);
                }

                $item->update(['projection' => $realAmount]);
            } else {
                for ($m = 1; $m <= 12; $m++) {
                    RkapBudgetItemProjection::create([
                        'rkap_budget_item_id' => $item->id,
                        'rkap_period_id' => $period->id,
                        'month' => $m,
                        'amount' => ($m === $allocMonth) ? $item->total_price : 0.0,
                        'inputted_by' => $verifikator->id,
                    ]);
                }
                $item->update(['projection' => $item->total_price]);
            }
        }
    }

    /**
     * Seed past year (2025) and next year (2027) data for Annual Comparison Chart.
     */
    protected function seedHistoricalComparisonData(array $users): void
    {
        $this->command->line('7. Seeding annual comparison datasets (2025 & 2027)...');

        $companyName = config('rkap.company_short_name', 'Perusahaan');
        $currentYear = (int) date('Y');
        $pastYear = $currentYear - 1;
        $nextYear = $currentYear + 1;

        $bureaus = $this->pickDemoBureaus(1);
        if (empty($bureaus)) {
            return;
        }
        $bureau = $bureaus[0];

        $wp = WorkPlan::first();
        $act = $wp?->activities?->first() ?? Activity::first();

        // 1. Past Year (e.g. 2025) - Finalized with approved historical performance
        $periodPast = RkapPeriod::updateOrCreate(
            ['year' => $pastYear],
            [
                'title' => "RKAP {$companyName} Tahun $pastYear",
                'description' => "Laporan Realisasi & Kinerja RKAP Tahun $pastYear (Tahun Lalu)",
                'status' => 'finalized',
                'submission_start' => now()->subYear()->startOfYear()->toDateString(),
                'submission_end' => now()->subYear()->endOfYear()->toDateString(),
            ]
        );

        $subPast = RkapSubmission::updateOrCreate(
            ['rkap_period_id' => $periodPast->id, 'bureau_id' => $bureau->id],
            [
                'created_by' => $users['biro']->id,
                'current_version' => 1,
                'status' => 'approved',
                'total_budget' => 820000000,
                'notes' => "Realisasi tahun buku $pastYear.",
            ]
        );
        $subPast->workPlans()->delete();

        $rwpPast = RkapWorkPlan::create([
            'rkap_submission_id' => $subPast->id,
            'work_plan_id' => $wp?->id,
            'activity_id' => $act?->id,
            'program_code' => 'PRG-HIST-01',
            'program_name' => 'Kinerja Operasional & Pendapatan Tahun ' . $pastYear,
            'description' => 'Rekapitulasi anggaran dan realisasi tahun lalu.',
            'sort_order' => 1,
            'approval_status' => 'approved',
        ]);

        // Revenue Item 2025
        $coaRev = $this->resolveCoa('410201', 'PL', 'PL0001');
        $biPastRev = RkapBudgetItem::create([
            'rkap_work_plan_id' => $rwpPast->id,
            'account_code' => $coaRev->code,
            'description' => 'Realisasi Pendapatan Layanan ' . $pastYear,
            'unit' => 'Tahun',
            'quantity' => 1,
            'unit_price' => 500000000,
            'total_price' => 500000000,
            'projection' => 485000000,
            'flow_direction' => 'in',
        ]);
        RkapBudgetItemRealization::create([
            'rkap_budget_item_id' => $biPastRev->id,
            'rkap_period_id' => $periodPast->id,
            'month' => 12,
            'amount' => 485000000,
            'uploaded_by' => $users['verifikator']->id,
            'uploaded_at' => now()->subYear(),
        ]);

        // Expense Item 2025
        $coaExp = $this->resolveCoa('580101', 'PL', 'PL0002');
        $biPastExp = RkapBudgetItem::create([
            'rkap_work_plan_id' => $rwpPast->id,
            'account_code' => $coaExp->code,
            'description' => 'Beban Operasional & Infrastruktur ' . $pastYear,
            'unit' => 'Tahun',
            'quantity' => 1,
            'unit_price' => 320000000,
            'total_price' => 320000000,
            'projection' => 310000000,
            'flow_direction' => 'out',
        ]);
        RkapBudgetItemRealization::create([
            'rkap_budget_item_id' => $biPastExp->id,
            'rkap_period_id' => $periodPast->id,
            'month' => 12,
            'amount' => 310000000,
            'uploaded_by' => $users['verifikator']->id,
            'uploaded_at' => now()->subYear(),
        ]);
        // Monthly distributions for 2025
        for ($m = 1; $m <= 12; $m++) {
            RkapBudgetItemMonthly::create([
                'rkap_budget_item_id' => $biPastRev->id, 'month' => $m, 'amount' => round(500000000 / 12, 2),
            ]);
            RkapBudgetItemCashOut::create([
                'rkap_budget_item_id' => $biPastRev->id, 'month' => $m, 'amount' => round(500000000 / 12, 2),
            ]);
            RkapBudgetItemMonthly::create([
                'rkap_budget_item_id' => $biPastExp->id, 'month' => $m, 'amount' => round(320000000 / 12, 2),
            ]);
            RkapBudgetItemCashOut::create([
                'rkap_budget_item_id' => $biPastExp->id, 'month' => $m, 'amount' => round(320000000 / 12, 2),
            ]);
        }
        $subPast->recalculateTotalBudget();

        // 2. Next Year (e.g. 2027) - Open proposed budget
        $periodNext = RkapPeriod::updateOrCreate(
            ['year' => $nextYear],
            [
                'title' => "RKAP {$companyName} Tahun $nextYear",
                'description' => "Penyusunan Rencana Kerja & Anggaran Tahun $nextYear (Tahun Depan)",
                'status' => 'open',
                'submission_start' => now()->startOfYear()->toDateString(),
                'submission_end' => now()->endOfYear()->toDateString(),
            ]
        );

        $subNext = RkapSubmission::updateOrCreate(
            ['rkap_period_id' => $periodNext->id, 'bureau_id' => $bureau->id],
            [
                'created_by' => $users['biro']->id,
                'current_version' => 1,
                'status' => 'submitted',
                'total_budget' => 1100000000,
                'notes' => "Usulan anggaran tahun $nextYear.",
            ]
        );
        $subNext->workPlans()->delete();

        $rwpNext = RkapWorkPlan::create([
            'rkap_submission_id' => $subNext->id,
            'work_plan_id' => $wp?->id,
            'activity_id' => $act?->id,
            'program_code' => 'PRG-FUT-01',
            'program_name' => 'Target Pertumbuhan Pendapatan & Usulan Anggaran Tahun ' . $nextYear,
            'description' => 'Rencana ekspansi bisnis dan belanja modal tahun depan.',
            'sort_order' => 1,
            'approval_status' => 'pending',
        ]);

        // Revenue Item 2027
        $biNextRev = RkapBudgetItem::create([
            'rkap_work_plan_id' => $rwpNext->id,
            'account_code' => $coaRev->code,
            'description' => 'Target Pendapatan Usaha ' . $nextYear,
            'unit' => 'Tahun',
            'quantity' => 1,
            'unit_price' => 700000000,
            'total_price' => 700000000,
            'projection' => 700000000,
            'flow_direction' => 'in',
        ]);

        // Expense Item 2027
        $biNextExp = RkapBudgetItem::create([
            'rkap_work_plan_id' => $rwpNext->id,
            'account_code' => $coaExp->code,
            'description' => 'Usulan Pagu Belanja Operasional ' . $nextYear,
            'unit' => 'Tahun',
            'quantity' => 1,
            'unit_price' => 400000000,
            'total_price' => 400000000,
            'projection' => 400000000,
            'flow_direction' => 'out',
        ]);

        // Monthly distributions for 2027
        for ($m = 1; $m <= 12; $m++) {
            RkapBudgetItemMonthly::create([
                'rkap_budget_item_id' => $biNextRev->id, 'month' => $m, 'amount' => round(700000000 / 12, 2),
            ]);
            RkapBudgetItemCashOut::create([
                'rkap_budget_item_id' => $biNextRev->id, 'month' => $m, 'amount' => round(700000000 / 12, 2),
            ]);
            RkapBudgetItemMonthly::create([
                'rkap_budget_item_id' => $biNextExp->id, 'month' => $m, 'amount' => round(400000000 / 12, 2),
            ]);
            RkapBudgetItemCashOut::create([
                'rkap_budget_item_id' => $biNextExp->id, 'month' => $m, 'amount' => round(400000000 / 12, 2),
            ]);
        }

        $subNext->recalculateTotalBudget();
    }

    /**
     * Seed approval audit trail based on how far the submission has progressed.
     */
    protected function seedApprovalTrail(RkapSubmission $submission, SubmissionStatus $status, array $users): void
    {
        $submission->approvals()->delete();

        // Build the approval chain up to the current status
        $fullChain = [
            ['gate' => SubmissionStatus::Submitted, 'user' => $users['biro'], 'role' => 'kepala_biro', 'action' => 'approved', 'comments' => 'Pengajuan diserahkan untuk review atasan.', 'days' => 40],
            ['gate' => SubmissionStatus::DeptApproved, 'user' => $users['dept'], 'role' => ApprovalRole::KepalaDepartemen->value, 'action' => ApprovalAction::Approved->value, 'comments' => 'Disetujui. Anggaran sesuai pagu indikatif.', 'days' => 35],
            ['gate' => SubmissionStatus::DirApproved, 'user' => $users['direksi'], 'role' => ApprovalRole::Direksi->value, 'action' => ApprovalAction::Approved->value, 'comments' => 'Disetujui tingkat pimpinan.', 'days' => 30],
            ['gate' => SubmissionStatus::VerifikatorApproved, 'user' => $users['verifikator'], 'role' => ApprovalRole::Verifikator->value, 'action' => ApprovalAction::Approved->value, 'comments' => 'Verifikasi selesai. COA dan kewajaran biaya memenuhi standar.', 'days' => 25],
            ['gate' => SubmissionStatus::Approved, 'user' => $users['dirut'], 'role' => ApprovalRole::DirekturUtama->value, 'action' => ApprovalAction::Approved->value, 'comments' => 'RKAP disetujui penuh untuk pelaksanaan anggaran tahun berjalan.', 'days' => 20],
        ];

        // Map status to the number of approval steps already completed
        $stepsForStatus = match ($status) {
            SubmissionStatus::Draft => 0,
            SubmissionStatus::Submitted => 0, // just submitted, no approval yet
            SubmissionStatus::DeptApproved, SubmissionStatus::DirReview => 1, // dept approved
            SubmissionStatus::DirApproved, SubmissionStatus::FinalReview => 2, // dept + dir approved
            SubmissionStatus::VerifikatorApproved, SubmissionStatus::PdirReview => 3, // dept + dir + verif
            SubmissionStatus::Approved => 5, // all steps
            default => 0,
        };

        for ($s = 0; $s < $stepsForStatus && $s < count($fullChain); $s++) {
            $step = $fullChain[$s];
            RkapApproval::create([
                'rkap_submission_id' => $submission->id,
                'user_id' => $step['user']->id,
                'version_number' => 1,
                'role' => $step['role'],
                'action' => $step['action'],
                'comments' => $step['comments'],
                'created_at' => now()->subDays($step['days']),
                'updated_at' => now()->subDays($step['days']),
            ]);
        }
    }

    /**
     * Seed 1 sample budget transfer to showcase the Budget Transfer module.
     */
    protected function seedSampleBudgetTransfer(
        RkapPeriod $period,
        Bureau $sourceBureau,
        Bureau $targetBureau,
        RkapSubmission $sourceSubmission,
        RkapWorkPlan $workPlan,
        RkapBudgetItem $budgetItem,
        array $users
    ): void {
        BudgetTransfer::where('source_bureau_id', $sourceBureau->id)->delete();

        $isSameDept = $sourceBureau->department_id === $targetBureau->department_id;

        $transfer = BudgetTransfer::create([
            'rkap_period_id' => $period->id,
            'source_bureau_id' => $sourceBureau->id,
            'target_bureau_id' => $targetBureau->id,
            'source_submission_id' => $sourceSubmission->id,
            'requested_by' => $users['biro']->id,
            'status' => BudgetTransferStatus::PendingSourceDept->value,
            'transfer_type' => $isSameDept ? 'intra_department' : 'inter_department',
            'notes' => 'Pergeseran sisa anggaran untuk percepatan program kerja unit tujuan.',
            'total_amount' => 15000000,
        ]);

        BudgetTransferItem::create([
            'budget_transfer_id' => $transfer->id,
            'rkap_work_plan_id' => $workPlan->id,
            'rkap_budget_item_id' => $budgetItem->id,
            'amount_transferred' => 15000000,
            'snapshot_data' => [
                'program_name' => $workPlan->program_name,
                'item_description' => $budgetItem->description,
                'account_code' => $budgetItem->account_code,
            ],
        ]);
    }

    /**
     * Seed realistic, balanced opening balances for the Balance Sheet showcase.
     * Accounting equation: Total Assets = Total Liabilities + Total Equity
     */
    protected function seedBalanceSheetOpeningBalances(RkapPeriod $period, array $users): void
    {
        $this->command->line('8. Seeding balanced Balance Sheet opening balances...');

        $adminId = $users['admin']->id ?? 1;

        // 1. Seed Active Period (e.g. 2026) - Balanced at 10.000.000.000 IDR
        BalanceSheetOpeningBalance::where('rkap_period_id', $period->id)->delete();

        $openings2026 = [
            // Aset Lancar (BS0001) - 4.000.000.000
            '110101' => 50000000,     // Kas Kecil Kantor Pusat: 50 Juta
            '110201' => 2450000000,   // Rekening Giro Bank Mandiri (IDR): 2.45 Miliar
            '110202' => 500000000,    // Rekening Giro Bank BCA (IDR): 500 Juta
            '112101' => 750000000,    // Piutang Usaha Domestik: 750 Juta
            '113101' => 250000000,    // Persediaan Barang Dagangan: 250 Juta

            // Aset Tidak Lancar (BS0002) - 6.000.000.000
            '121101' => 2500000000,   // Tanah Hak Milik: 2.5 Miliar
            '122101' => 2500000000,   // Bangunan Gedung Kantor & Fasilitas: 2.5 Miliar
            '124101' => 1500000000,   // Mesin, Server IT & Peralatan: 1.5 Miliar
            '122199' => -500000000,   // Akumulasi Penyusutan Gedung: -500 Juta

            // Liabilitas Lancar (BS0003) - 1.000.000.000
            '211101' => 600000000,    // Utang Usaha Pemasok: 600 Juta
            '212101' => 250000000,    // Beban Akrual Gaji: 250 Juta
            '213101' => 150000000,    // Utang Pajak PPh 21: 150 Juta

            // Liabilitas Tidak Lancar (BS0004) - 2.000.000.000
            '221101' => 2000000000,   // Pinjaman Bank Jangka Panjang (Kredit Investasi): 2 Miliar

            // Ekuitas (BS0005) - 7.000.000.000
            '311101' => 5000000000,   // Modal Saham Disetor Penuh: 5 Miliar
            '321101' => 2000000000,   // Saldo Laba Ditahan Periode Lalu: 2 Miliar
            '322101' => 0,            // Laba / Rugi Tahun Berjalan: 0
        ];

        foreach ($openings2026 as $code => $amount) {
            $coa = Coa::where('code', $code)->first();
            if ($coa) {
                BalanceSheetOpeningBalance::create([
                    'rkap_period_id' => $period->id,
                    'coa_id' => $coa->id,
                    'amount' => $amount,
                    'created_by' => $adminId,
                    'updated_by' => $adminId,
                ]);
            }
        }

        // 2. Also seed comparison periods (2025 and 2027) so all dropdown views remain balanced
        $pastPeriod = RkapPeriod::where('year', $period->year - 1)->first();
        if ($pastPeriod) {
            BalanceSheetOpeningBalance::where('rkap_period_id', $pastPeriod->id)->delete();
            $openings2025 = $openings2026;
            $openings2025['110201'] = 2100000000;
            $openings2025['122199'] = -350000000;
            $openings2025['321101'] = 1800000000;

            foreach ($openings2025 as $code => $amount) {
                $coa = Coa::where('code', (string) $code)->first();
                if ($coa) {
                    BalanceSheetOpeningBalance::create([
                        'rkap_period_id' => $pastPeriod->id,
                        'coa_id' => $coa->id,
                        'amount' => $amount,
                        'created_by' => $adminId,
                        'updated_by' => $adminId,
                    ]);
                }
            }
        }

        $nextPeriod = RkapPeriod::where('year', $period->year + 1)->first();
        if ($nextPeriod) {
            BalanceSheetOpeningBalance::where('rkap_period_id', $nextPeriod->id)->delete();
            $openings2027 = $openings2026;
            $openings2027['110201'] = 2180000000;
            $openings2027['122101'] = 2600000000;
            $openings2027['321101'] = 1830000000;

            foreach ($openings2027 as $code => $amount) {
                $coa = Coa::where('code', (string) $code)->first();
                if ($coa) {
                    BalanceSheetOpeningBalance::create([
                        'rkap_period_id' => $nextPeriod->id,
                        'coa_id' => $coa->id,
                        'amount' => $amount,
                        'created_by' => $adminId,
                        'updated_by' => $adminId,
                    ]);
                }
            }
        }
    }
}
