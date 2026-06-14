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
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ── 1. Roles ──
        $roleAdmin         = Role::firstOrCreate(['name' => 'admin']);
        $roleKepalaBiro    = Role::firstOrCreate(['name' => 'kepala_biro']);
        $roleKepalaDept    = Role::firstOrCreate(['name' => 'kepala_departemen']);
        $roleDireksi       = Role::firstOrCreate(['name' => 'direksi']);
        $roleVerifikator   = Role::firstOrCreate(['name' => 'verifikator']);

        // ── 2. Permissions ──
        $permissions = [
            // RKAP
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
            // Compilation — scoped from narrowest to widest
            'rkap.compilation.dept', // see own dept/bureau submissions
            'rkap.compilation.dir',  // see own directorate submissions
            'rkap.compilation.all',  // see all directorates
            'rkap.realization.upload',
            'rkap.projection.input',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // ── 3. Assign Permissions to Roles ──
        $roleAdmin->syncPermissions(Permission::all());

        $roleKepalaBiro->syncPermissions([
            'rkap.create',
            'rkap.edit',
            'rkap.delete',
            'rkap.submit',
            'rkap.view.own',
            'rkap.comment',
            'rkap.show',
            'rkap.compilation.dept', // can see their own department compilation
            'rkap.projection.input',
        ]);

        $roleKepalaDept->syncPermissions([
            'rkap.view.dept',
            'rkap.review.dept',
            'rkap.approve.dept',
            'rkap.comment',
            'rkap.show',
            'rkap.compilation.dept', // can see their own department compilation
        ]);

        $roleDireksi->syncPermissions([
            'rkap.view.dept',
            'rkap.review.dir',
            'rkap.approve.dir',
            'rkap.comment',
            'rkap.compilation.dept', // inherits dept scope
            'rkap.compilation.dir',  // can see their own directorate compilation
        ]);

        $roleVerifikator->syncPermissions([
            'rkap.view.all',
            'rkap.review.final',
            'rkap.approve.final',
            'rkap.comment',
            'rkap.compilation.dept', // inherits
            'rkap.compilation.dir',  // inherits
            'rkap.compilation.all',  // can see all directorates
            'rkap.realization.upload',
        ]);

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
            ['name' => 'HSR Director', 'description' => 'Direktur HSR', 'is_active' => true]
        );

        $departments = [
            ['directorate_code' => 'HR', 'code' => 'HRH', 'name' => 'Human Resources', 'is_verifier' => false, 'is_active' => true],
            ['directorate_code' => 'HH', 'code' => 'HHF', 'name' => 'Fixed Assets Maintanance', 'is_verifier' => false, 'is_active' => true],
            ['directorate_code' => 'HH', 'code' => 'HHE', 'name' => 'EMU Maintanance', 'is_verifier' => false, 'is_active' => true],
            ['directorate_code' => 'HF', 'code' => 'HFF', 'name' => 'Finance and Budget Management', 'is_verifier' => false, 'is_active' => true],
            ['directorate_code' => 'HU', 'code' => 'HUL', 'name' => 'Legal', 'is_verifier' => false, 'is_active' => true],
            ['directorate_code' => 'HH', 'code' => 'HHO', 'name' => 'Operation', 'is_verifier' => false, 'is_active' => true],
            ['directorate_code' => 'HF', 'code' => 'HFI', 'name' => 'Investment and Risk Management', 'is_verifier' => false, 'is_active' => true],
            ['directorate_code' => 'HP', 'code' => 'HPR', 'name' => 'Railway Business', 'is_verifier' => false, 'is_active' => true],
            ['directorate_code' => 'HP', 'code' => 'HPY', 'name' => 'non Railway Business Development', 'is_verifier' => false, 'is_active' => true],
            ['directorate_code' => 'HP', 'code' => 'HPP', 'name' => 'PMO & Network Expansion', 'is_verifier' => false, 'is_active' => true],
            ['directorate_code' => 'HR', 'code' => 'HRA', 'name' => 'Assets Managment & Land Acquisition', 'is_verifier' => false, 'is_active' => true],
            ['directorate_code' => 'HR', 'code' => 'HRG', 'name' => 'General Affair', 'is_verifier' => false, 'is_active' => true],
            ['directorate_code' => 'HR', 'code' => 'HRS', 'name' => 'SSHE', 'is_verifier' => false, 'is_active' => true],
            ['directorate_code' => 'HP', 'code' => 'HPI', 'name' => 'IT & Ticketing Facilities', 'is_verifier' => false, 'is_active' => true],
            ['directorate_code' => 'HH', 'code' => 'HHT', 'name' => 'Technical Design Management', 'is_verifier' => false, 'is_active' => true],
            ['directorate_code' => 'HU', 'code' => 'HUI', 'name' => 'Audit Internal', 'is_verifier' => false, 'is_active' => true],
            ['directorate_code' => 'HU', 'code' => 'HUC', 'name' => 'Corporate Secretary', 'is_verifier' => false, 'is_active' => true],
            ['directorate_code' => 'HU', 'code' => 'HUP', 'name' => 'Corporate Planning & Assurance', 'is_verifier' => false, 'is_active' => true],
            ['directorate_code' => 'HF', 'code' => 'HFA', 'name' => 'Information System and Business Analyst', 'is_verifier' => true, 'is_active' => true],
            ['directorate_code' => 'HF', 'code' => 'HFL', 'name' => 'Logistic', 'is_verifier' => false, 'is_active' => true],
            ['directorate_code' => 'HH', 'code' => 'HHC', 'name' => 'Construction', 'is_verifier' => false, 'is_active' => true],
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
            ['department_code' => 'HFA', 'code' => 'HFAB', 'name' => 'Business Analytic', 'is_active' => true],
            ['department_code' => 'HFA', 'code' => 'HFAI', 'name' => 'Information system Integration', 'is_active' => true],
            ['department_code' => 'HFF', 'code' => 'HFFA', 'name' => 'Accounting', 'is_active' => true],
            ['department_code' => 'HFF', 'code' => 'HFFB', 'name' => 'Budget', 'is_active' => true],
            ['department_code' => 'HFF', 'code' => 'HFFF', 'name' => 'Finance', 'is_active' => true],
            ['department_code' => 'HFF', 'code' => 'HFFT', 'name' => 'Tax', 'is_active' => true],
            ['department_code' => 'HFI', 'code' => 'HFIE', 'name' => 'Export and Import Management', 'is_active' => true],
            ['department_code' => 'HFI', 'code' => 'HFIV', 'name' => 'Investment', 'is_active' => true],
            ['department_code' => 'HFI', 'code' => 'HFIR', 'name' => 'Risk Management', 'is_active' => true],
            ['department_code' => 'HFL', 'code' => 'HFLG', 'name' => 'General Material, Custom, and Supply Chain', 'is_active' => true],
            ['department_code' => 'HFL', 'code' => 'HFLE', 'name' => 'Planning & Evaluation', 'is_active' => true],
            ['department_code' => 'HFL', 'code' => 'HFLP', 'name' => 'Procurement', 'is_active' => true],
            ['department_code' => 'HHC', 'code' => 'HHCE', 'name' => 'EMU & Operation Facility', 'is_active' => true],
            ['department_code' => 'HHC', 'code' => 'HHCQ', 'name' => 'Quality Control And Assurance', 'is_active' => true],
            ['department_code' => 'HHC', 'code' => 'HHCC', 'name' => 'Railway System Construction', 'is_active' => true],
            ['department_code' => 'HHC', 'code' => 'HHCB', 'name' => 'Section Bandung', 'is_active' => true],
            ['department_code' => 'HHC', 'code' => 'HHCJ', 'name' => 'Section Jakarta', 'is_active' => true],
            ['department_code' => 'HHE', 'code' => 'HHEQ', 'name' => 'EMU Quality', 'is_active' => true],
            ['department_code' => 'HHE', 'code' => 'HHET', 'name' => 'EMU Technology', 'is_active' => true],
            ['department_code' => 'HHE', 'code' => 'HHES', 'name' => 'Maintenance Support', 'is_active' => true],
            ['department_code' => 'HHE', 'code' => 'HHEC', 'name' => 'Maintenance Train Crew', 'is_active' => true],
            ['department_code' => 'HHE', 'code' => 'HHEO', 'name' => 'Operational Maintenance', 'is_active' => true],
            ['department_code' => 'HHF', 'code' => 'HHFC', 'name' => 'Communication Maintenance', 'is_active' => true],
            ['department_code' => 'HHF', 'code' => 'HHFI', 'name' => 'Comprehensive Inspection', 'is_active' => true],
            ['department_code' => 'HHF', 'code' => 'HHFT', 'name' => 'Comprehensive Technology', 'is_active' => true],
            ['department_code' => 'HHF', 'code' => 'HHFB', 'name' => 'Comprehensive Technology of Permanent Way and Building', 'is_active' => true],
            ['department_code' => 'HHF', 'code' => 'HHFO', 'name' => 'Comprehensive Technology of Power Supply', 'is_active' => true],
            ['department_code' => 'HHF', 'code' => 'HHFL', 'name' => 'Comprehensive Technology of Signaling', 'is_active' => true],
            ['department_code' => 'HHF', 'code' => 'HHFD', 'name' => 'Dispatching', 'is_active' => true],
            ['department_code' => 'HHF', 'code' => 'HHFP', 'name' => 'Heavy Machinery Operation and Maintenance', 'is_active' => true],
            ['department_code' => 'HHF', 'code' => 'HHFY', 'name' => 'Permanent Way', 'is_active' => true],
            ['department_code' => 'HHF', 'code' => 'HHFP', 'name' => 'Permanent Way and Building Maintenance', 'is_active' => true],
            ['department_code' => 'HHF', 'code' => 'HHFS', 'name' => 'Signaling', 'is_active' => true],
            ['department_code' => 'HHO', 'code' => 'HHOE', 'name' => 'EMU Maintenance', 'is_active' => true],
            ['department_code' => 'HHO', 'code' => 'HHOM', 'name' => 'Equipment Maintenance', 'is_active' => true],
            ['department_code' => 'HHO', 'code' => 'HHOI', 'name' => 'Infrastructure Maintenance', 'is_active' => true],
            ['department_code' => 'HHO', 'code' => 'HHOG', 'name' => 'Integration Railway System', 'is_active' => true],
            ['department_code' => 'HHO', 'code' => 'HHOX', 'name' => 'OCC', 'is_active' => true],
            ['department_code' => 'HHO', 'code' => 'HHOO', 'name' => 'Operation', 'is_active' => true],
            ['department_code' => 'HHO', 'code' => 'HHOP', 'name' => 'Operation Planning', 'is_active' => true],
            ['department_code' => 'HHO', 'code' => 'HHOW', 'name' => 'Operation Train Crew', 'is_active' => true],
            ['department_code' => 'HHO', 'code' => 'HHOF', 'name' => 'Passenger Facility', 'is_active' => true],
            ['department_code' => 'HHO', 'code' => 'HHOT', 'name' => 'Passenger Service on Train', 'is_active' => true],
            ['department_code' => 'HHO', 'code' => 'HHOQ', 'name' => 'Quality', 'is_active' => true],
            ['department_code' => 'HHO', 'code' => 'HHOK', 'name' => 'Schedule and Integration', 'is_active' => true],
            ['department_code' => 'HHO', 'code' => 'HHOS', 'name' => 'Station Passenger Service', 'is_active' => true],
            ['department_code' => 'HHO', 'code' => 'HHOD', 'name' => 'Train Crew (Driver Management)', 'is_active' => true],
            ['department_code' => 'HHT', 'code' => 'HHTC', 'name' => 'Civil and Trackwork', 'is_active' => true],
            ['department_code' => 'HHT', 'code' => 'HHTE', 'name' => 'Railway System & EMU', 'is_active' => true],
            ['department_code' => 'HHT', 'code' => 'HHTF', 'name' => 'Station Buildings & Facilities', 'is_active' => true],
            ['department_code' => 'HPI', 'code' => 'HPIO', 'name' => 'IT Data Center and Operation Network', 'is_active' => true],
            ['department_code' => 'HPI', 'code' => 'HPIC', 'name' => 'IT Office & Corporate Support', 'is_active' => true],
            ['department_code' => 'HPI', 'code' => 'HPIT', 'name' => 'IT Ticketing Facilities', 'is_active' => true],
            ['department_code' => 'HPP', 'code' => 'HPPN', 'name' => 'Network Expansion Project', 'is_active' => true],
            ['department_code' => 'HPP', 'code' => 'HPPC', 'name' => 'PMO Construction', 'is_active' => true],
            ['department_code' => 'HPP', 'code' => 'HPPO', 'name' => 'PMO OM Readiness', 'is_active' => true],
            ['department_code' => 'HPP', 'code' => 'HPPG', 'name' => 'Project Government and Compliance', 'is_active' => true],
            ['department_code' => 'HPP', 'code' => 'HPPI', 'name' => 'Project Integration Management', 'is_active' => true],
            ['department_code' => 'HPR', 'code' => 'HPRC', 'name' => 'Customer Care', 'is_active' => true],
            ['department_code' => 'HPR', 'code' => 'HPRI', 'name' => 'Information System and Technology', 'is_active' => true],
            ['department_code' => 'HPR', 'code' => 'HPRM', 'name' => 'Marketing Business Development', 'is_active' => true],
            ['department_code' => 'HPR', 'code' => 'HPRS', 'name' => 'Passenger Ticketing and Sales', 'is_active' => true],
            ['department_code' => 'HPY', 'code' => 'HPYD', 'name' => 'Business Development', 'is_active' => true],
            ['department_code' => 'HPY', 'code' => 'HPYO', 'name' => 'Business Operation', 'is_active' => true],
            ['department_code' => 'HPY', 'code' => 'HPYB', 'name' => 'Business Support', 'is_active' => true],
            ['department_code' => 'HPY', 'code' => 'HPYP', 'name' => 'Marketing and Partnership', 'is_active' => true],
            ['department_code' => 'HPY', 'code' => 'HPYN', 'name' => 'Non-Farebox Business Development', 'is_active' => true],
            ['department_code' => 'HPY', 'code' => 'HPYM', 'name' => 'Tenant Relation and Collection', 'is_active' => true],
            ['department_code' => 'HRA', 'code' => 'HRAL', 'name' => 'Land Acquisition', 'is_active' => true],
            ['department_code' => 'HRA', 'code' => 'HRAC', 'name' => 'Land Certification', 'is_active' => true],
            ['department_code' => 'HRA', 'code' => 'HRAN', 'name' => 'Non Railway Asset', 'is_active' => true],
            ['department_code' => 'HRA', 'code' => 'HRAR', 'name' => 'Railway Asset', 'is_active' => true],
            ['department_code' => 'HRG', 'code' => 'HRGD', 'name' => 'Depot General Affair and Dormitory', 'is_active' => true],
            ['department_code' => 'HRG', 'code' => 'HRGA', 'name' => 'General Affair & Protocol', 'is_active' => true],
            ['department_code' => 'HRG', 'code' => 'HRGS', 'name' => 'General Affair Support System', 'is_active' => true],
            ['department_code' => 'HRH', 'code' => 'HRHO', 'name' => 'HR Organization Development', 'is_active' => true],
            ['department_code' => 'HRH', 'code' => 'HRHT', 'name' => 'HR Talent & Career Management', 'is_active' => true],
            ['department_code' => 'HRH', 'code' => 'HRHI', 'name' => 'Industrial Relation and Employee Regulation', 'is_active' => true],
            ['department_code' => 'HRH', 'code' => 'HRHP', 'name' => 'Personnel Care and Administration', 'is_active' => true],
            ['department_code' => 'HRH', 'code' => 'HRHD', 'name' => 'Training And Certification', 'is_active' => true],
            ['department_code' => 'HRS', 'code' => 'HRSH', 'name' => 'Health & Environment', 'is_active' => true],
            ['department_code' => 'HRS', 'code' => 'HRSS', 'name' => 'Safety', 'is_active' => true],
            ['department_code' => 'HRS', 'code' => 'HRSR', 'name' => 'Safety Railway', 'is_active' => true],
            ['department_code' => 'HRS', 'code' => 'HRSC', 'name' => 'Security', 'is_active' => true],
            ['department_code' => 'HRS', 'code' => 'HRSV', 'name' => 'Vital Object Security', 'is_active' => true],
            ['department_code' => 'HUC', 'code' => 'HUCC', 'name' => 'Corporate Communication', 'is_active' => true],
            ['department_code' => 'HUC', 'code' => 'HUCR', 'name' => 'Corporate Relation', 'is_active' => true],
            ['department_code' => 'HUC', 'code' => 'HUCG', 'name' => 'General Affair and Protocol', 'is_active' => true],
            ['department_code' => 'HUC', 'code' => 'HUCS', 'name' => 'Secretariat and Archive Management', 'is_active' => true],
            ['department_code' => 'HUI', 'code' => 'HUIA', 'name' => 'Audit', 'is_active' => true],
            ['department_code' => 'HUI', 'code' => 'HUIB', 'name' => 'Audit 2', 'is_active' => true],
            ['department_code' => 'HUI', 'code' => 'HUIP', 'name' => 'Program, Administration, Monitoring & Evaluation', 'is_active' => true],
            ['department_code' => 'HUL', 'code' => 'HULD', 'name' => 'Dispute Resolution and Regulation', 'is_active' => true],
            ['department_code' => 'HUL', 'code' => 'HULC', 'name' => 'Legal Corporate and Business', 'is_active' => true],
            ['department_code' => 'HUP', 'code' => 'HUPA', 'name' => 'Archive Management', 'is_active' => true],
            ['department_code' => 'HUP', 'code' => 'HUPC', 'name' => 'Corporate Planning', 'is_active' => true]
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

        // ── 5. Sample Users ──
        // Fetch key departments and bureaus from database for reference
        $deptHFA = Department::where('code', 'HFA')->first(); // IS & Business Analyst (verifier)
        $deptHFA2 = Department::where('code', 'HFT')->first(); // Finance and Budget Management
        $deptHPI = Department::where('code', 'HPI')->first(); // IT & Ticketing Facilities
        $deptHRH = Department::where('code', 'HRH')->first(); // Human Resources
        $deptHHO = Department::where('code', 'HHO')->first(); // Operation

        $bureauHFAB = Bureau::where('code', 'HFAB')->first(); // Business Analytic
        $bureauHFAI = Bureau::where('code', 'HFAI')->first(); // Information system Integration
        $bureauHPIO = Bureau::where('code', 'HPIO')->first(); // IT Data Center
        $bureauHRHT = Bureau::where('code', 'HRHT')->first(); // HR Talent & Career Management
        $bureauHHOO = Bureau::where('code', 'HHOO')->first(); // Operation Bureau

        // Admin (already created by RoleAndUserSeeder, just update org if needed)
        $admin = User::where('email', 'admin@rkap.com')->first();
        if ($admin) {
            $admin->update(['directorate_id' => $dirHU->id, 'position' => 'Administrator Sistem']);
        }

        // Kepala Biro — Business Analytic Bureau
        $kb1 = User::firstOrCreate(
            ['email' => 'kabiro.ba@rkap.com'],
            [
                'name' => 'Budi Santoso',
                'password' => Hash::make('password'),
                'bureau_id' => $bureauHFAB->id,
                'department_id' => $deptHFA->id,
                'directorate_id' => $dirHF->id,
                'position' => 'Kepala Biro Business Analytic',
            ]
        );
        $kb1->syncRoles([$roleKepalaBiro]);

        // Kepala Biro — Information System Integration Bureau
        $kb2 = User::firstOrCreate(
            ['email' => 'kabiro.isi@rkap.com'],
            [
                'name' => 'Siti Rahayu',
                'password' => Hash::make('password'),
                'bureau_id' => $bureauHFAI->id,
                'department_id' => $deptHFA->id,
                'directorate_id' => $dirHF->id,
                'position' => 'Kepala Biro Information System Integration',
            ]
        );
        $kb2->syncRoles([$roleKepalaBiro]);

        // Kepala Departemen — Information System and Business Analyst (Verifier Dept)
        $kd1 = User::firstOrCreate(
            ['email' => 'kadept.hfa@rkap.com'],
            [
                'name' => 'Andi Wijaya',
                'password' => Hash::make('password'),
                'department_id' => $deptHFA->id,
                'directorate_id' => $dirHF->id,
                'position' => 'Kepala Departemen IS & Business Analyst',
            ]
        );
        $kd1->syncRoles([$roleKepalaDept]);

        // Kepala Departemen — Human Resources
        $kd2 = User::firstOrCreate(
            ['email' => 'kadept.hrh@rkap.com'],
            [
                'name' => 'Rini Kusuma',
                'password' => Hash::make('password'),
                'department_id' => $deptHRH->id,
                'directorate_id' => $dirHR->id,
                'position' => 'Kepala Departemen Human Resources',
            ]
        );
        $kd2->syncRoles([$roleKepalaDept]);

        // Direksi — HR, Asset, & SSHE Directorate
        $dir1 = User::firstOrCreate(
            ['email' => 'direksi.hr@rkap.com'],
            [
                'name' => 'Dr. Hendra Gunawan',
                'password' => Hash::make('password'),
                'directorate_id' => $dirHR->id,
                'position' => 'HR, Asset, & SSHE Director',
            ]
        );
        $dir1->syncRoles([$roleDireksi]);

        // Direksi — Finance & Risk Directorate
        $dir2 = User::firstOrCreate(
            ['email' => 'direksi.finance@rkap.com'],
            [
                'name' => 'Ir. Dewi Purnama',
                'password' => Hash::make('password'),
                'directorate_id' => $dirHF->id,
                'position' => 'Finance & Risk Director',
            ]
        );
        $dir2->syncRoles([$roleDireksi]);

        // Verifikator — IS & Business Analyst Department (Verifier Dept)
        $ver = User::firstOrCreate(
            ['email' => 'verifikator@rkap.com'],
            [
                'name' => 'Ahmad Fauzi',
                'password' => Hash::make('password'),
                'department_id' => $deptHFA->id,
                'directorate_id' => $dirHF->id,
                'position' => 'Verifikator - IS & Business Analyst',
            ]
        );
        $ver->syncRoles([$roleVerifikator]);

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

        $this->command->info('RKAP Seeder completed: roles, permissions, org structure, sample users, and master data created.');
    }
}
