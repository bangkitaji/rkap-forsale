<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\User;
use App\Models\Directorate;
use App\Models\Department;
use App\Models\Bureau;

class RkapSeeder extends Seeder
{
    public function run(): void
    {
        // if (app()->environment('production')) {
        //     return;
        // }

        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ── 1. Roles ──
        $roleNames = config('rkap.roles', [
            'admin'             => 'admin',
            'user'              => 'user',
            'kepala_biro'       => 'kepala_biro',
            'kepala_departemen' => 'kepala_departemen',
            'direksi'           => 'direksi',
            'verifikator'       => 'verifikator',
            'direktur_utama'    => 'direktur_utama',
        ]);

        $roleAdmin         = Role::firstOrCreate(['name' => $roleNames['admin'] ?? 'admin']);
        $roleKepalaBiro    = Role::firstOrCreate(['name' => $roleNames['kepala_biro'] ?? 'kepala_biro']);
        $roleKepalaDept    = Role::firstOrCreate(['name' => $roleNames['kepala_departemen'] ?? 'kepala_departemen']);
        $roleDireksi       = Role::firstOrCreate(['name' => $roleNames['direksi'] ?? 'direksi']);
        $roleVerifikator   = Role::firstOrCreate(['name' => $roleNames['verifikator'] ?? 'verifikator']);
        $rolePresident     = Role::firstOrCreate(['name' => $roleNames['direktur_utama'] ?? 'direktur_utama']);

        // ── 2. Permissions ──
        $permissions = [
            'dashboard.show',
            // RKAP
            'rkap.show',
            'rkap.review.president',
            'rkap.approve.president',
            'rkap.create',
            'rkap.edit',
            'rkap.delete',
            'rkap.submit',
            'rkap.view.own',
            'rkap.view.dept',
            'rkap.view.all',
            // Approval
            'rkap.review.dept',
            'rkap.approve.dept',
            'rkap.review.dir',
            'rkap.approve.dir',
            'rkap.review.final',
            'rkap.approve.final',
            // Period
            'rkap.manage.period',
            // Organisation
            'organization.manage',
            // Comment
            'rkap.comment',
            // Settings Satuan
            'settings.satuan.manage',
            // Master Data
            'masterdata.show',
            'masterdata.workplan.view',
            'masterdata.activity.view',
            'masterdata.workplan.manage',
            'masterdata.activity.manage',
            'masterdata.coa.manage',
            'masterdata.coagroup.manage',
            'masterdata.coaprofitloss.manage',
            // Compilation — scoped from narrowest to widest
            'rkap.compilation.dept', // see own dept/bureau submissions
            'rkap.compilation.dir',  // see own directorate submissions
            'rkap.compilation.all',  // see all directorates
            'rkap.realization.upload',
            'rkap.projection.input',
            'rkap.projection.view',
            'analytics.summary.dept',
            'analytics.cds.view',
            'analytics.openingbalance.manage',
            'analytics.balancesheet.view',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // ── 3. Assign Permissions to Roles ──
        $roleAdmin->syncPermissions(Permission::all());

        $roleKepalaBiro->syncPermissions([
            'dashboard.show',
            'rkap.create',
            'rkap.edit',
            'rkap.delete',
            'rkap.submit',
            'rkap.view.own',
            'rkap.comment',
            'rkap.show',
            'rkap.compilation.dept', // can see their own department compilation
            'rkap.projection.input',
            'rkap.projection.view',
            'masterdata.workplan.view',
            'masterdata.activity.view',
        ]);

        $roleUser = Role::firstOrCreate(['name' => 'user']);
        $roleUser->syncPermissions([
            'dashboard.show',
            'rkap.create',
            'rkap.edit',
            'rkap.delete',
            'rkap.submit',
            'rkap.view.own',
            'rkap.comment',
            'rkap.show',
            'rkap.compilation.dept',
            'rkap.projection.input',
            'rkap.projection.view',
            'masterdata.workplan.view',
            'masterdata.activity.view',
        ]);

        $roleKepalaDept->syncPermissions([
            'dashboard.show',
            'rkap.view.dept',
            'rkap.review.dept',
            'rkap.approve.dept',
            'rkap.comment',
            'rkap.show',
            'rkap.compilation.dept', // can see their own department compilation
            'rkap.projection.view',
            'masterdata.workplan.view',
            'masterdata.activity.view',
        ]);

        $roleDireksi->syncPermissions([
            'dashboard.show',
            'rkap.view.dept',
            'rkap.review.dir',
            'rkap.approve.dir',
            'rkap.comment',
            'rkap.compilation.dept', // inherits dept scope
            'rkap.compilation.dir',  // can see their own directorate compilation
            'rkap.projection.view',
            'rkap.show',
            'masterdata.workplan.view',
            'masterdata.activity.view',
        ]);

        $roleVerifikator->syncPermissions([
            'dashboard.show',
            'rkap.view.all',
            'rkap.review.final',
            'rkap.approve.final',
            'rkap.comment',
            'rkap.compilation.dept', // inherits
            'rkap.compilation.dir',  // inherits
            'rkap.compilation.all',  // can see all directorates
            'rkap.realization.upload',
            'rkap.projection.input',
            'rkap.projection.view',
            'rkap.show',
            'masterdata.workplan.view',
            'masterdata.activity.view',
            'analytics.summary.dept',
            'analytics.cds.view',
            'analytics.openingbalance.manage',
            'analytics.balancesheet.view',
        ]);

        $rolePresident->syncPermissions([
            'dashboard.show',
            'rkap.view.all',
            'rkap.review.president',
            'rkap.approve.president',
            'rkap.comment',
            'rkap.compilation.dept',
            'rkap.compilation.dir',
            'rkap.compilation.all',
            'rkap.projection.view',
            'rkap.show',
            'masterdata.workplan.view',
            'masterdata.activity.view',
        ]);

        // Assign rkap.show to all roles
        // ── 3b. Seed Standard Period and Reference COA Groups ──
        $currentYear = (int) date('Y');
        \App\Models\RkapPeriod::firstOrCreate(
            ['year' => $currentYear],
            [
                'title' => "RKAP Tahun $currentYear",
                'description' => "Periode Penyusunan RKAP Tahun $currentYear",
                'status' => 'draft',
                'submission_start' => now()->startOfYear()->toDateString(),
                'submission_end' => now()->endOfYear()->toDateString(),
            ]
        );

        $coaGroups = [
            ['code' => '100000', 'name' => 'Aset (Assets)', 'description' => 'Sumber daya ekonomi yang dikendalikan oleh perusahaan (Kas, Piutang, Aset Tetap, dll).'],
            ['code' => '200000', 'name' => 'Liabilitas (Liabilities)', 'description' => 'Kewajiban finansial perusahaan masa kini (Utang Usaha, Pinjaman Bank, dll).'],
            ['code' => '300000', 'name' => 'Ekuitas (Equity)', 'description' => 'Hak residual atas aset perusahaan setelah dikurangi semua liabilitas (Modal Saham, Laba Ditahan, dll).'],
            ['code' => '400000', 'name' => 'Pendapatan (Revenue)', 'description' => 'Penerimaan/Arus masuk bruto dari aktivitas normal entitas (Pendapatan Operasional, Penjualan, dll).'],
            ['code' => '510000', 'name' => 'Beban Pegawai (Employee Expenses)', 'description' => 'Seluruh pengeluaran untuk gaji, tunjangan, jaminan sosial, dan fasilitas karyawan.'],
            ['code' => '520000', 'name' => 'Beban Operasional (Operating Expenses / OPEX)', 'description' => 'Biaya operasional rutin non-kepegawaian seperti sewa, listrik, air, perlengkapan kantor, perbaikan.'],
            ['code' => '530000', 'name' => 'Beban Investasi / Modal (Capital Expenditures / CAPEX)', 'description' => 'Pengeluaran untuk perolehan atau peningkatan kapasitas aset tetap/investasi modal jangka panjang.'],
            ['code' => '600000', 'name' => 'Pendapatan/Beban Non-Operasional (Non-Operating)', 'description' => 'Pendapatan dan beban dari aktivitas di luar kegiatan usaha utama (Pendapatan bunga, denda, pajak, dll).'],
        ];

        foreach ($coaGroups as $group) {
            \App\Models\CoaGroup::firstOrCreate(
                ['code' => $group['code']],
                ['name' => $group['name'], 'description' => $group['description']]
            );
        }

        if (!config('rkap.seed_sample_data', false)) {
            $this->command->info('RKAP baseline initialized (roles, permissions, period, COA groups). Sample org & users skipped (RKAP_SEED_SAMPLE_DATA=false).');
            return;
        }

        // ── 4. Sample Organization ──
        $dirHU = Directorate::firstOrCreate(
            ['code' => 'HU'],
            ['name' => 'President Director', 'description' => 'Direktur Utama', 'is_active' => true]
        );
        $dirHR = Directorate::firstOrCreate(
            ['code' => 'HR'],
            ['name' => 'HR, Asset, & SSHE Director', 'description' => 'Direktur HR, Aset, dan SSHE', 'is_active' => true]
        );
        $dirHF = Directorate::firstOrCreate(
            ['code' => 'HF'],
            ['name' => 'Finance & Risk Director', 'description' => 'Direktur Keuangan dan Resiko', 'is_active' => true]
        );
        $dirHP = Directorate::firstOrCreate(
            ['code' => 'HP'],
            ['name' => 'Project Management & Business Development Director', 'description' => 'Direktur Manajemen Proyek dan Pengembangan Bisnis', 'is_active' => true]
        );
        $dirHH = Directorate::firstOrCreate(
            ['code' => 'HH'],
            ['name' => 'Operations & Technical Director', 'description' => 'Direktur Operasional dan Teknik', 'is_active' => true]
        );

        $departments = [
            ['directorate_code' => 'HR', 'code' => 'HRH', 'name' => 'Human Resources', 'is_verifier' => false, 'is_active' => true],
            ['directorate_code' => 'HH', 'code' => 'HHF', 'name' => 'Fixed Assets Maintenance', 'is_verifier' => false, 'is_active' => true],
            ['directorate_code' => 'HH', 'code' => 'HHE', 'name' => 'Fleet & Equipment Maintenance', 'is_verifier' => false, 'is_active' => true],
            ['directorate_code' => 'HF', 'code' => 'HFF', 'name' => 'Finance and Budget Management', 'is_verifier' => false, 'is_active' => true],
            ['directorate_code' => 'HU', 'code' => 'HUL', 'name' => 'Legal', 'is_verifier' => false, 'is_active' => true],
            ['directorate_code' => 'HH', 'code' => 'HHO', 'name' => 'Operation', 'is_verifier' => false, 'is_active' => true],
            ['directorate_code' => 'HF', 'code' => 'HFI', 'name' => 'Investment and Risk Management', 'is_verifier' => false, 'is_active' => true],
            ['directorate_code' => 'HP', 'code' => 'HPR', 'name' => 'Core Business & Commercial', 'is_verifier' => false, 'is_active' => true],
            ['directorate_code' => 'HP', 'code' => 'HPY', 'name' => 'Commercial & Partnership Development', 'is_verifier' => false, 'is_active' => true],
            ['directorate_code' => 'HP', 'code' => 'HPP', 'name' => 'PMO & Network Expansion', 'is_verifier' => false, 'is_active' => true],
            ['directorate_code' => 'HR', 'code' => 'HRA', 'name' => 'Assets Management & Facilities', 'is_verifier' => false, 'is_active' => true],
            ['directorate_code' => 'HR', 'code' => 'HRG', 'name' => 'General Affair', 'is_verifier' => false, 'is_active' => true],
            ['directorate_code' => 'HR', 'code' => 'HRS', 'name' => 'SSHE', 'is_verifier' => false, 'is_active' => true],
            ['directorate_code' => 'HP', 'code' => 'HPI', 'name' => 'IT & Digital Facilities', 'is_verifier' => false, 'is_active' => true],
            ['directorate_code' => 'HH', 'code' => 'HHT', 'name' => 'Technical Design Management', 'is_verifier' => false, 'is_active' => true],
            ['directorate_code' => 'HU', 'code' => 'HUI', 'name' => 'Audit Internal', 'is_verifier' => false, 'is_active' => true],
            ['directorate_code' => 'HU', 'code' => 'HUC', 'name' => 'Corporate Secretary', 'is_verifier' => false, 'is_active' => true],
            ['directorate_code' => 'HU', 'code' => 'HUP', 'name' => 'Corporate Planning & Assurance', 'is_verifier' => false, 'is_active' => true],
            ['directorate_code' => 'HF', 'code' => 'HFA', 'name' => 'Information System and Business Analyst', 'is_verifier' => true, 'is_active' => true],
            ['directorate_code' => 'HF', 'code' => 'HFL', 'name' => 'Logistic', 'is_verifier' => false, 'is_active' => true],
            ['directorate_code' => 'HH', 'code' => 'HHC', 'name' => 'Infrastructure Construction', 'is_verifier' => false, 'is_active' => true],
        ];

        $directorateMap = Directorate::whereIn('code', collect($departments)->pluck('directorate_code')->unique()->values())
            ->get()
            ->keyBy('code');

        foreach ($departments as $departmentData) {
            $directorate = $directorateMap->get($departmentData['directorate_code']);

            if (!$directorate) {
                $this->command->warn("Directorate with code {$departmentData['directorate_code']} not found. Skipping department {$departmentData['code']}.");
                continue;
            }

            Department::updateOrCreate(
                ['code' => $departmentData['code']],
                [
                    'directorate_id' => $directorate->id,
                    'name' => $departmentData['name'],
                    'is_verifier' => $departmentData['is_verifier'],
                    'is_active' => $departmentData['is_active'],
                ]
            );
        }

        $bureaus = [
            ['department_code' => 'HUC', 'code' => 'HUCC', 'name' => 'CORPORATE COMMUNICATION', 'is_active' => true],
            ['department_code' => 'HUC', 'code' => 'HUCR', 'name' => 'CORPORATE RELATION', 'is_active' => true],
            ['department_code' => 'HUC', 'code' => 'HUCS', 'name' => 'SECRETARY', 'is_active' => true],
            ['department_code' => 'HUI', 'code' => 'HUIA', 'name' => 'AUDIT 1', 'is_active' => true],
            ['department_code' => 'HUI', 'code' => 'HUIB', 'name' => 'AUDIT 2', 'is_active' => true],
            ['department_code' => 'HUI', 'code' => 'HUIC', 'name' => 'AUDIT 3', 'is_active' => true],
            ['department_code' => 'HUI', 'code' => 'HUIP', 'name' => 'AUDIT ADMINISTRATION', 'is_active' => true],
            ['department_code' => 'HUL', 'code' => 'HULC', 'name' => 'LEGAL CORPORATE AND BUSINESS', 'is_active' => true],
            ['department_code' => 'HUL', 'code' => 'HULD', 'name' => 'DISPUTE RESOLUTION AND REGULATION', 'is_active' => true],
            ['department_code' => 'HUP', 'code' => 'HUPP', 'name' => 'CORPORATE PLANNING', 'is_active' => true],
            ['department_code' => 'HUP', 'code' => 'HUPG', 'name' => 'QUALITY ASSURANCE AND GOOD CORPORATE GOVERNANCE', 'is_active' => true],
            ['department_code' => 'HUP', 'code' => 'HUPA', 'name' => 'ARCHIVE MANAGEMENT', 'is_active' => true],
            ['department_code' => 'HFF', 'code' => 'HFFF', 'name' => 'FINANCE', 'is_active' => true],
            ['department_code' => 'HFF', 'code' => 'HFFB', 'name' => 'BUDGET', 'is_active' => true],
            ['department_code' => 'HFF', 'code' => 'HFFT', 'name' => 'TAX', 'is_active' => true],
            ['department_code' => 'HFF', 'code' => 'HFFA', 'name' => 'ACCOUNTING', 'is_active' => true],
            ['department_code' => 'HFI', 'code' => 'HFII', 'name' => 'INVESTMENT', 'is_active' => true],
            ['department_code' => 'HFI', 'code' => 'HFIR', 'name' => 'RISK MANAGEMENT', 'is_active' => true],
            ['department_code' => 'HFI', 'code' => 'HFIE', 'name' => 'EXPORT AND IMPORT MANAGEMENT', 'is_active' => true],
            ['department_code' => 'HFA', 'code' => 'HFAI', 'name' => 'INFORMATION SYSTEM INTEGRATION', 'is_active' => true],
            ['department_code' => 'HFA', 'code' => 'HFAB', 'name' => 'BUSINESS ANALYSIS', 'is_active' => true],
            ['department_code' => 'HFL', 'code' => 'HFLE', 'name' => 'PROCUREMENT PLANNING AND EVALUATION', 'is_active' => true],
            ['department_code' => 'HFL', 'code' => 'HFLP', 'name' => 'PROCUREMENT', 'is_active' => true],
            ['department_code' => 'HFL', 'code' => 'HFLG', 'name' => 'GENERAL MATERIAL, CUSTOM AND SUPPLY CHAIN', 'is_active' => true],
            ['department_code' => 'HRH', 'code' => 'HRHO', 'name' => 'ORGANIZATION DEVELOPMENT', 'is_active' => true],
            ['department_code' => 'HRH', 'code' => 'HRHT', 'name' => 'TALENT MANAGEMENT', 'is_active' => true],
            ['department_code' => 'HRH', 'code' => 'HRHP', 'name' => 'PERSONNEL CARE AND ADMINISTRATION', 'is_active' => true],
            ['department_code' => 'HRH', 'code' => 'HRHI', 'name' => 'INDUSTRIAL RELATION AND EMPLOYEE REGULATION', 'is_active' => true],
            ['department_code' => 'HRH', 'code' => 'HRHD', 'name' => 'TRAINING AND CERTIFICATION', 'is_active' => true],
            ['department_code' => 'HRS', 'code' => 'HRSR', 'name' => 'OPERATIONAL SAFETY', 'is_active' => true],
            ['department_code' => 'HRS', 'code' => 'HRSH', 'name' => 'HEALTH AND ENVIRONMENT', 'is_active' => true],
            ['department_code' => 'HRS', 'code' => 'HRSS', 'name' => 'SAFETY STANDARD', 'is_active' => true],
            ['department_code' => 'HRS', 'code' => 'HRSO', 'name' => 'OPERATIONAL SECURITY', 'is_active' => true],
            ['department_code' => 'HRS', 'code' => 'HRSV', 'name' => 'VITAL OBJECT SECURITY', 'is_active' => true],
            ['department_code' => 'HRA', 'code' => 'HRAR', 'name' => 'CORE OPERATIONAL ASSET', 'is_active' => true],
            ['department_code' => 'HRA', 'code' => 'HRAN', 'name' => 'COMMERCIAL & SUPPORTING ASSET', 'is_active' => true],
            ['department_code' => 'HRA', 'code' => 'HRAL', 'name' => 'LAND ACQUISITION', 'is_active' => true],
            ['department_code' => 'HRA', 'code' => 'HRAC', 'name' => 'LAND CERTIFICATION', 'is_active' => true],
            ['department_code' => 'HRG', 'code' => 'HRGA', 'name' => 'GENERAL AFFAIR AND PROTOCOL', 'is_active' => true],
            ['department_code' => 'HRG', 'code' => 'HRGD', 'name' => 'DEPOT GENERAL AFFAIR AND DORMITORY', 'is_active' => true],
            ['department_code' => 'HRG', 'code' => 'HRGS', 'name' => 'GENERAL AFFAIR SUPPORT SYSTEM', 'is_active' => true],
            ['department_code' => 'HPP', 'code' => 'HPPG', 'name' => 'PROJECT GOVERNANCE AND COMPLIANCE', 'is_active' => true],
            ['department_code' => 'HPP', 'code' => 'HPPN', 'name' => 'NETWORK EXPANSION PROJECT', 'is_active' => true],
            ['department_code' => 'HPP', 'code' => 'HPPI', 'name' => 'PROJECT INTEGRATION MANAGEMENT', 'is_active' => true],
            ['department_code' => 'HPR', 'code' => 'HPRM', 'name' => 'MARKETING AND DEVELOPMENT', 'is_active' => true],
            ['department_code' => 'HPR', 'code' => 'HPRS', 'name' => 'SALES', 'is_active' => true],
            ['department_code' => 'HPR', 'code' => 'HPRC', 'name' => 'CUSTOMER CARE', 'is_active' => true],
            ['department_code' => 'HPY', 'code' => 'HPYD', 'name' => 'BUSINESS DEVELOPMENT', 'is_active' => true],
            ['department_code' => 'HPY', 'code' => 'HPYP', 'name' => 'MARKETING AND PARTNERSHIP', 'is_active' => true],
            ['department_code' => 'HPY', 'code' => 'HPYB', 'name' => 'BUSINESS SUPPORT', 'is_active' => true],
            ['department_code' => 'HPY', 'code' => 'HPYM', 'name' => 'TENANT RELATION AND COLLECTION', 'is_active' => true],
            ['department_code' => 'HPY', 'code' => 'HPYO', 'name' => 'BUSINESS OPERATION', 'is_active' => true],
            ['department_code' => 'HPI', 'code' => 'HPIC', 'name' => 'IT OFFICE AND CORPORATE SUPPORT IT', 'is_active' => true],
            ['department_code' => 'HPI', 'code' => 'HPIT', 'name' => 'IT TRANSACTION & CUSTOMER FACILITIES', 'is_active' => true],
            ['department_code' => 'HPI', 'code' => 'HPIO', 'name' => 'IT DATA CENTER AND OPERATION NETWORK IT', 'is_active' => true],
            ['department_code' => 'HHT', 'code' => 'HHTC', 'name' => 'CIVIL AND INFRASTRUCTURE WORK', 'is_active' => true],
            ['department_code' => 'HHT', 'code' => 'HHTE', 'name' => 'OPERATIONAL FLEET & EQUIPMENT SYSTEM', 'is_active' => true],
            ['department_code' => 'HHT', 'code' => 'HHTF', 'name' => 'FACILITY AND SERVICE BUILDINGS', 'is_active' => true],
            ['department_code' => 'HHC', 'code' => 'HHCC', 'name' => 'INFRASTRUCTURE CONSTRUCTION', 'is_active' => true],
            ['department_code' => 'HHC', 'code' => 'HHCQ', 'name' => 'QUALITY CONTROL AND ASSURANCE', 'is_active' => true],
            ['department_code' => 'HHC', 'code' => 'HHCO', 'name' => 'FLEET AND OPERATION FACILITY', 'is_active' => true],
            ['department_code' => 'HHO', 'code' => 'HHOP', 'name' => 'OPERATION PLANNING', 'is_active' => true],
            ['department_code' => 'HHO', 'code' => 'HHOC', 'name' => 'OPERATION CONTROL CENTER (OCC)', 'is_active' => true],
            ['department_code' => 'HHO', 'code' => 'HHOD', 'name' => 'FIELD CREW & OPERATORS MANAGEMENT', 'is_active' => true],
            ['department_code' => 'HHO', 'code' => 'HHOT', 'name' => 'SERVICE DELIVERY ON OPERATIONS', 'is_active' => true],
            ['department_code' => 'HHO', 'code' => 'HHOS', 'name' => 'CUSTOMER SERVICE AT FACILITIES', 'is_active' => true],
            ['department_code' => 'HHO', 'code' => 'HHOF', 'name' => 'CUSTOMER & USER FACILITY', 'is_active' => true],
            ['department_code' => 'HHE', 'code' => 'HHEC', 'name' => 'FIELD MAINTENANCE CREW', 'is_active' => true],
            ['department_code' => 'HHE', 'code' => 'HHEO', 'name' => 'OPERATIONAL', 'is_active' => true],
            ['department_code' => 'HHE', 'code' => 'HHES', 'name' => 'MAINTENANCE SUPPORT', 'is_active' => true],
            ['department_code' => 'HHE', 'code' => 'HHET', 'name' => 'TECHNOLOGY AND QUALITY', 'is_active' => true],
            ['department_code' => 'HHF', 'code' => 'HHFI', 'name' => 'COMPREHENSIVE INSPECTION', 'is_active' => true],
            ['department_code' => 'HHF', 'code' => 'HHFT', 'name' => 'COMPREHENSIVE TECHNOLOGY OF COMMUNICATION', 'is_active' => true],
            ['department_code' => 'HHF', 'code' => 'HHFL', 'name' => 'COMPREHENSIVE TECHNOLOGY OF SIGNALING', 'is_active' => true],
            ['department_code' => 'HHF', 'code' => 'HHFO', 'name' => 'COMPREHENSIVE TECHNOLOGY OF POWER SUPPLY', 'is_active' => true],
            ['department_code' => 'HHF', 'code' => 'HHFB', 'name' => 'COMPREHENSIVE INFRASTRUCTURE TECHNOLOGY', 'is_active' => true],
            ['department_code' => 'HHF', 'code' => 'HHFD', 'name' => 'DISPATCHING', 'is_active' => true],
            ['department_code' => 'HHF', 'code' => 'HHFH', 'name' => 'HEAVY MACHINERY OPERATION AND MAINTENANCE', 'is_active' => true],
            ['department_code' => 'HHF', 'code' => 'HHFC', 'name' => 'COMMUNICATION MAINTENANCE', 'is_active' => true],
            ['department_code' => 'HHF', 'code' => 'HHFS', 'name' => 'SIGNALING MAINTENANCE', 'is_active' => true],
            ['department_code' => 'HHF', 'code' => 'HHFW', 'name' => 'POWER SUPPLY MAINTENANCE', 'is_active' => true],
            ['department_code' => 'HHF', 'code' => 'HHFP', 'name' => 'INFRASTRUCTURE AND BUILDING MAINTENANCE', 'is_active' => true]
        ];

        $departmentCodes = collect($bureaus)->pluck('department_code')->unique()->values();
        $departmentMap = Department::whereIn('code', $departmentCodes)->get()->keyBy('code');

        foreach ($bureaus as $bureauData) {
            $department = $departmentMap->get($bureauData['department_code']);

            if (!$department) {
                $this->command->warn("Department with code {$bureauData['department_code']} not found. Skipping bureau {$bureauData['code']}.");
                continue;
            }

            Bureau::updateOrCreate(
                ['code' => $bureauData['code']],
                [
                    'department_id' => $department->id,
                    'name' => $bureauData['name'],
                    'is_active' => $bureauData['is_active'],
                ]
            );
        }

        // ── 5. Seed Users from Org Structure ──
        $adminEmail = config('rkap.admin_email', 'admin@rkap.com');
        $domain     = config('rkap.email_domain', 'example.com');

        // Reset non-admin users first to keep db clean
        User::where('email', '!=', $adminEmail)->delete();

        // Admin (already created by RoleAndUserSeeder, just update org if needed)
        $admin = User::where('email', $adminEmail)->first();
        if ($admin) {
            $admin->update(['directorate_id' => $dirHU->id, 'position' => 'Administrator Sistem']);
        }

        // Helper to format acronyms in titles
        $formatTitle = function ($title) {
            return str_ireplace(
                ['Emu', 'It', 'Occ', 'Gcg', 'Sshe', 'Hsr', 'Pmo', 'Ba', 'Coa'],
                ['EMU', 'IT', 'OCC', 'GCG', 'SSHE', 'HSR', 'PMO', 'BA', 'COA'],
                ucwords(strtolower(trim($title)))
            );
        };

        // 1. Seed Directorate Users (Direksi & President Director)
        foreach (Directorate::all() as $dir) {
            $email = strtolower($dir->code) . '@' . $domain;

            if ($dir->code === 'HU') {
                $position = $dir->name; // President Director
                $role = $rolePresident;
            } else {
                $cleanName = str_replace(' Director', '', $dir->name);
                $position = "Director of " . $formatTitle($cleanName);
                $role = $roleDireksi;
            }

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $dir->code,
                    'password' => Hash::make(config('rkap.seed_default_password', 'P@ssw0rd!')),
                    'directorate_id' => $dir->id,
                    'position' => $position,
                ]
            );
            $user->syncRoles([$role]);
        }

        // 2. Seed Department Users (Kepala Departemen)
        foreach (Department::all() as $dept) {
            $email = strtolower($dept->code) . '@' . $domain;
            $position = "GM of " . $formatTitle($dept->name);

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $dept->code,
                    'password' => Hash::make(config('rkap.seed_default_password', 'P@ssw0rd!')),
                    'department_id' => $dept->id,
                    'directorate_id' => $dept->directorate_id,
                    'position' => $position,
                ]
            );
            $user->syncRoles([$roleKepalaDept]);
        }

        // 3. Seed Bureau Users (Kepala Biro)
        foreach (Bureau::all() as $bureau) {
            $email = strtolower($bureau->code) . '@' . $domain;
            $cleanName = preg_replace('/^(senior\s+)?manager\s+of\s+/i', '', $bureau->name);
            $position = "Manager of " . $formatTitle($cleanName);

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $bureau->code,
                    'password' => Hash::make(config('rkap.seed_default_password', 'P@ssw0rd!')),
                    'bureau_id' => $bureau->id,
                    'department_id' => $bureau->department_id,
                    'directorate_id' => $bureau->department?->directorate_id,
                    'position' => $position,
                ]
            );
            $user->syncRoles([$roleKepalaBiro]);
        }

        // 4. Seed Specialist User
        $bureauBusinessAnalysis = Bureau::where('code', 'HFAB')->first();
        $specialist = User::firstOrCreate(
            ['email' => 'specialist@' . $domain],
            [
                'name' => 'Specialist User',
                'password' => Hash::make(config('rkap.seed_default_password', 'P@ssw0rd!')),
                'bureau_id' => $bureauBusinessAnalysis?->id,
                'department_id' => $bureauBusinessAnalysis?->department_id,
                'directorate_id' => $bureauBusinessAnalysis?->department?->directorate_id,
                'position' => 'Specialist Of Business Analytic',
            ]
        );
        $specialist->syncRoles([$roleVerifikator]);

        // ── 6. Sample Master Data (WorkPlans, Activities, COAs) ──
        $wp1 = \App\Models\WorkPlan::firstOrCreate(
            ['code' => '1000000001'],
            ['title' => 'Penyusutan Langsung']
        );
        $wp2 = \App\Models\WorkPlan::firstOrCreate(
            ['code' => '1000000002'],
            ['title' => 'Penyusutan Tidak Langsung']
        );
        $wp3 = \App\Models\WorkPlan::firstOrCreate(
            ['code' => '1000000003'],
            ['title' => 'Pelaporan Keuangan']
        );
        $wp4 = \App\Models\WorkPlan::firstOrCreate(
            ['code' => '1000000004'],
            ['title' => 'Pelaporan Pajak']
        );

        $workPlanMap = [
            '1000000001' => $wp1->id,
            '1000000002' => $wp2->id,
            '1000000003' => $wp3->id,
            '1000000004' => $wp4->id,
        ];

        $activities = [
            ['work_plan_code' => '1000000001', 'code' => '2000000001', 'title' => 'Penyusutan Langsung'],
            ['work_plan_code' => '1000000002', 'code' => '2000000002', 'title' => 'Penyusutan Tidak Langsung'],
        ];

        $seededActivities = [];
        foreach ($activities as $activityData) {
            $workPlanId = $workPlanMap[$activityData['work_plan_code']] ?? null;

            if (!$workPlanId) {
                $this->command->warn("Work plan with code {$activityData['work_plan_code']} not found. Skipping activity {$activityData['code']}.");
                continue;
            }

            $seededActivities[$activityData['code']] = \App\Models\Activity::updateOrCreate(
                ['code' => $activityData['code']],
                [
                    'work_plan_id' => $workPlanId,
                    'title' => $activityData['title'],
                ]
            );
        }

        // $coa1 = \App\Models\Coa::firstOrCreate(
        //     ['code' => '660000001'],
        //     [
        //         'title' => 'Training and Development',
        //         'description' => 'COA SAP - Training and Development.'
        //     ]
        // );
        // $coa2 = \App\Models\Coa::firstOrCreate(
        //     ['code' => '610000030'],
        //     [
        //         'title' => 'Licensing Expenses',
        //         'description' => 'COA SAP - Licensing Expenses.'
        //     ]
        // );
        // $coa3 = \App\Models\Coa::firstOrCreate(
        //     ['code' => '670000001'],
        //     [
        //         'title' => 'Teknologi Informasi',
        //         'description' => 'COA SAP - Teknologi Informasi.'
        //     ]
        // );

        // Sync some initial mappings for available seeded activities
        // if (isset($seededActivities['PK-01-01'])) {
        //     $seededActivities['PK-01-01']->coas()->syncWithoutDetaching([$coa1->id, $coa2->id]);
        // }
        // if (isset($seededActivities['PK-02-01'])) {
        //     $seededActivities['PK-02-01']->coas()->syncWithoutDetaching([$coa2->id, $coa3->id]);
        // }
        // if (isset($seededActivities['PK-03-01'])) {
        //     $seededActivities['PK-03-01']->coas()->syncWithoutDetaching([$coa3->id]);
        // }

        // ── 5. Seed Reference COA Groups ──
        $coaGroups = [
            [
                'code' => '100000',
                'name' => 'Aset (Assets)',
                'description' => 'Sumber daya ekonomi yang dikendalikan oleh perusahaan (Kas, Piutang, Aset Tetap, dll).'
            ],
            [
                'code' => '200000',
                'name' => 'Liabilitas (Liabilities)',
                'description' => 'Kewajiban finansial perusahaan masa kini (Utang Usaha, Pinjaman Bank, dll).'
            ],
            [
                'code' => '300000',
                'name' => 'Ekuitas (Equity)',
                'description' => 'Hak residual atas aset perusahaan setelah dikurangi semua liabilitas (Modal Saham, Laba Ditahan, dll).'
            ],
            [
                'code' => '400000',
                'name' => 'Pendapatan (Revenue)',
                'description' => 'Penerimaan/Arus masuk bruto dari aktivitas normal entitas (Pendapatan Operasional, Penjualan, dll).'
            ],
            [
                'code' => '510000',
                'name' => 'Beban Pegawai (Employee Expenses)',
                'description' => 'Seluruh pengeluaran untuk gaji, tunjangan, jaminan sosial, dan fasilitas karyawan.'
            ],
            [
                'code' => '520000',
                'name' => 'Beban Operasional (Operating Expenses / OPEX)',
                'description' => 'Biaya operasional rutin non-kepegawaian seperti sewa, listrik, air, perlengkapan kantor, perbaikan.'
            ],
            [
                'code' => '530000',
                'name' => 'Beban Investasi / Modal (Capital Expenditures / CAPEX)',
                'description' => 'Pengeluaran untuk perolehan atau peningkatan kapasitas aset tetap/investasi modal jangka panjang.'
            ],
            [
                'code' => '600000',
                'name' => 'Pendapatan/Beban Non-Operasional (Non-Operating)',
                'description' => 'Pendapatan dan beban dari aktivitas di luar kegiatan usaha utama (Pendapatan bunga, denda, pajak, dll).'
            ],
        ];

        foreach ($coaGroups as $group) {
            \App\Models\CoaGroup::firstOrCreate(
                ['code' => $group['code']],
                [
                    'name' => $group['name'],
                    'description' => $group['description']
                ]
            );
        }

        $this->command->info('RKAP Seeder completed: roles, permissions, org structure, sample users, and master data created.');
    }
}
