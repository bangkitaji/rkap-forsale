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
        $rolePresident     = Role::firstOrCreate(['name' => 'president_director']);

        // ── 2. Permissions ──
        $permissions = [
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
            'rkap.projection.view',
        ]);

        $roleKepalaDept->syncPermissions([
            'rkap.view.dept',
            'rkap.review.dept',
            'rkap.approve.dept',
            'rkap.comment',
            'rkap.show',
            'rkap.compilation.dept', // can see their own department compilation
            'rkap.projection.view',
        ]);

        $roleDireksi->syncPermissions([
            'rkap.view.dept',
            'rkap.review.dir',
            'rkap.approve.dir',
            'rkap.comment',
            'rkap.compilation.dept', // inherits dept scope
            'rkap.compilation.dir',  // can see their own directorate compilation
            'rkap.projection.view',
            'rkap.show',
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
            'rkap.projection.input',
            'rkap.projection.view',
            'rkap.show',
        ]);

        $rolePresident->syncPermissions([
            'rkap.view.all',
            'rkap.review.president',
            'rkap.approve.president',
            'rkap.comment',
            'rkap.compilation.dept',
            'rkap.compilation.dir',
            'rkap.compilation.all',
            'rkap.projection.view',
            'rkap.show',
        ]);

        // Assign rkap.show to all roles
        $rkapShowPerm = Permission::firstOrCreate(['name' => 'rkap.show']);
        foreach (Role::all() as $role) {
            $role->givePermissionTo($rkapShowPerm);
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
            ['department_code' => 'HUC', 'code' => 'HUCC', 'name' => 'MANAGER OF CORPORATE COMMUNICATION', 'is_active' => true],
            ['department_code' => 'HUC', 'code' => 'HUCR', 'name' => 'MANAGER OF CORPORATE RELATION', 'is_active' => true],
            ['department_code' => 'HUC', 'code' => 'HUCS', 'name' => 'MANAGER OF SECRETARY', 'is_active' => true],
            ['department_code' => 'HUI', 'code' => 'HUIA', 'name' => 'MANAGER OF AUDIT 1', 'is_active' => true],
            ['department_code' => 'HUI', 'code' => 'HUIB', 'name' => 'MANAGER OF AUDIT 2', 'is_active' => true],
            ['department_code' => 'HUI', 'code' => 'HUIC', 'name' => 'MANAGER OF AUDIT 3', 'is_active' => true],
            ['department_code' => 'HUI', 'code' => 'HUIP', 'name' => 'MANAGER OF AUDIT ADMINISTRATION', 'is_active' => true],
            ['department_code' => 'HUL', 'code' => 'HULC', 'name' => 'SENIOR MANAGER OF LEGAL CORPORATE AND BUSINESS', 'is_active' => true],
            ['department_code' => 'HUL', 'code' => 'HULD', 'name' => 'SENIOR MANAGER OF DISPUTE RESOLUTION AND REGULATION', 'is_active' => true],
            ['department_code' => 'HUP', 'code' => 'HUPP', 'name' => 'MANAGER OF CORPORATE PLANNING', 'is_active' => true],
            ['department_code' => 'HUP', 'code' => 'HUPG', 'name' => 'MANAGER OF QUALITY ASSURANCE AND GOOD CORPORATE GOVERNANCE', 'is_active' => true],
            ['department_code' => 'HUP', 'code' => 'HUPA', 'name' => 'MANAGER OF ARCHIVE MANAGEMENT', 'is_active' => true],
            ['department_code' => 'HFF', 'code' => 'HFFF', 'name' => 'MANAGER OF FINANCE', 'is_active' => true],
            ['department_code' => 'HFF', 'code' => 'HFFB', 'name' => 'MANAGER OF BUDGET', 'is_active' => true],
            ['department_code' => 'HFF', 'code' => 'HFFT', 'name' => 'MANAGER OF TAX', 'is_active' => true],
            ['department_code' => 'HFF', 'code' => 'HFFA', 'name' => 'MANAGER OF ACCOUNTING', 'is_active' => true],
            ['department_code' => 'HFI', 'code' => 'HFII', 'name' => 'MANAGER OF INVESTMENT', 'is_active' => true],
            ['department_code' => 'HFI', 'code' => 'HFIR', 'name' => 'MANAGER OF RISK MANAGEMENT', 'is_active' => true],
            ['department_code' => 'HFI', 'code' => 'HFIE', 'name' => 'MANAGER OF EXPORT AND IMPORT MANAGEMENT', 'is_active' => true],
            ['department_code' => 'HFA', 'code' => 'HFAI', 'name' => 'MANAGER OF INFORMATION SYSTEM INTEGRATION', 'is_active' => true],
            ['department_code' => 'HFA', 'code' => 'HFAB', 'name' => 'MANAGER OF BUSINESS ANALYSIS', 'is_active' => true],
            ['department_code' => 'HFL', 'code' => 'HFLE', 'name' => 'MANAGER OF PROCUREMENT PLANNING AND EVALUATION', 'is_active' => true],
            ['department_code' => 'HFL', 'code' => 'HFLP', 'name' => 'MANAGER OF PROCUREMENT', 'is_active' => true],
            ['department_code' => 'HFL', 'code' => 'HFLG', 'name' => 'MANAGER OF GENERAL MATERIAL, CUSTOM AND SUPPLY CHAIN', 'is_active' => true],
            ['department_code' => 'HRH', 'code' => 'HRHO', 'name' => 'MANAGER OF ORGANIZATION DEVELOPMENT', 'is_active' => true],
            ['department_code' => 'HRH', 'code' => 'HRHT', 'name' => 'MANAGER OF TALENT MANAGEMENT', 'is_active' => true],
            ['department_code' => 'HRH', 'code' => 'HRHP', 'name' => 'MANAGER OF PERSONNEL CARE AND ADMINISTRATION', 'is_active' => true],
            ['department_code' => 'HRH', 'code' => 'HRHI', 'name' => 'MANAGER OF INDUSTRIAL RELATION AND EMPLOYEE REGULATION', 'is_active' => true],
            ['department_code' => 'HRH', 'code' => 'HRHD', 'name' => 'MANAGER OF TRAINING AND CERTIFICATION', 'is_active' => true],
            ['department_code' => 'HRS', 'code' => 'HRSR', 'name' => 'MANAGER OF SAFETY RAILWAY', 'is_active' => true],
            ['department_code' => 'HRS', 'code' => 'HRSH', 'name' => 'MANAGER OF HEALTH AND ENVIRONMENT', 'is_active' => true],
            ['department_code' => 'HRS', 'code' => 'HRSS', 'name' => 'MANAGER OF SAFETY STANDARD', 'is_active' => true],
            ['department_code' => 'HRS', 'code' => 'HRSO', 'name' => 'MANAGER OF RAILWAY OPERATION SECURITY', 'is_active' => true],
            ['department_code' => 'HRS', 'code' => 'HRSV', 'name' => 'MANAGER OF VITAL OBJECT SECURITY', 'is_active' => true],
            ['department_code' => 'HRA', 'code' => 'HRAR', 'name' => 'MANAGER OF RAILWAY ASSET', 'is_active' => true],
            ['department_code' => 'HRA', 'code' => 'HRAN', 'name' => 'MANAGER OF NON-RAILWAY ASSET', 'is_active' => true],
            ['department_code' => 'HRA', 'code' => 'HRAL', 'name' => 'MANAGER OF LAND ACQUISITION', 'is_active' => true],
            ['department_code' => 'HRA', 'code' => 'HRAC', 'name' => 'MANAGER OF LAND CERTIFICATION', 'is_active' => true],
            ['department_code' => 'HRG', 'code' => 'HRGA', 'name' => 'MANAGER OF GENERAL AFFAIR AND PROTOCOL', 'is_active' => true],
            ['department_code' => 'HRG', 'code' => 'HRGD', 'name' => 'MANAGER OF DEPOT GENERAL AFFAIR AND DORMITORY', 'is_active' => true],
            ['department_code' => 'HRG', 'code' => 'HRGS', 'name' => 'MANAGER OF GENERAL AFFAIR SUPPORT SYSTEM', 'is_active' => true],
            ['department_code' => 'HPP', 'code' => 'HPPG', 'name' => 'MANAGER OF PROJECT GOVERNANCE AND COMPLIANCE', 'is_active' => true],
            ['department_code' => 'HPP', 'code' => 'HPPN', 'name' => 'MANAGER OF NETWORK EXPANSION PROJECT', 'is_active' => true],
            ['department_code' => 'HPP', 'code' => 'HPPI', 'name' => 'MANAGER OF PROJECT INTEGRATION MANAGEMENT', 'is_active' => true],
            ['department_code' => 'HPR', 'code' => 'HPRM', 'name' => 'MANAGER OF MARKETING AND DEVELOPMENT', 'is_active' => true],
            ['department_code' => 'HPR', 'code' => 'HPRS', 'name' => 'MANAGER OF SALES', 'is_active' => true],
            ['department_code' => 'HPR', 'code' => 'HPRC', 'name' => 'MANAGER OF CUSTOMER CARE', 'is_active' => true],
            ['department_code' => 'HPY', 'code' => 'HPYD', 'name' => 'MANAGER OF BUSINESS DEVELOPMENT', 'is_active' => true],
            ['department_code' => 'HPY', 'code' => 'HPYP', 'name' => 'MANAGER OF MARKETING AND PARTNERSHIP', 'is_active' => true],
            ['department_code' => 'HPY', 'code' => 'HPYB', 'name' => 'MANAGER OF BUSINESS SUPPORT', 'is_active' => true],
            ['department_code' => 'HPY', 'code' => 'HPYM', 'name' => 'MANAGER OF TENANT RELATION AND COLLECTION', 'is_active' => true],
            ['department_code' => 'HPY', 'code' => 'HPYO', 'name' => 'MANAGER OF BUSINESS OPERATION', 'is_active' => true],
            ['department_code' => 'HPI', 'code' => 'HPIC', 'name' => 'MANAGER OF IT OFFICE AND CORPORATE SUPPORT IT', 'is_active' => true],
            ['department_code' => 'HPI', 'code' => 'HPIT', 'name' => 'MANAGER OF IT TICKETING FACILITIES IT', 'is_active' => true],
            ['department_code' => 'HPI', 'code' => 'HPIO', 'name' => 'MANAGER OF IT DATA CENTER AND OPERATION NETWORK IT', 'is_active' => true],
            ['department_code' => 'HHT', 'code' => 'HHTC', 'name' => 'MANAGER OF CIVIL AND TRACKWORK', 'is_active' => true],
            ['department_code' => 'HHT', 'code' => 'HHTE', 'name' => 'MANAGER OF RAILWAY SYSTEM AND EMU', 'is_active' => true],
            ['department_code' => 'HHT', 'code' => 'HHTF', 'name' => 'MANAGER OF STATION BUILDING AND FACILITIES', 'is_active' => true],
            ['department_code' => 'HHC', 'code' => 'HHCC', 'name' => 'MANAGER OF RAILWAY CONSTRUCTION', 'is_active' => true],
            ['department_code' => 'HHC', 'code' => 'HHCQ', 'name' => 'MANAGER OF QUALITY CONTROL AND ASSURANCE', 'is_active' => true],
            ['department_code' => 'HHC', 'code' => 'HHCO', 'name' => 'MANAGER OF EMU AND OPERATION FACILITY', 'is_active' => true],
            ['department_code' => 'HHO', 'code' => 'HHOP', 'name' => 'MANAGER OF OPERATION PLANNING', 'is_active' => true],
            ['department_code' => 'HHO', 'code' => 'HHOC', 'name' => 'MANAGER OF OPERATION CONTROL CENTER (OCC)', 'is_active' => true],
            ['department_code' => 'HHO', 'code' => 'HHOD', 'name' => 'MANAGER OF TRAIN CREW (DRIVER MANAGEMENT)', 'is_active' => true],
            ['department_code' => 'HHO', 'code' => 'HHOT', 'name' => 'MANAGER OF PASSENGER SERVICE ON TRAIN', 'is_active' => true],
            ['department_code' => 'HHO', 'code' => 'HHOS', 'name' => 'MANAGER OF PASSENGER SERVICE ON STATION', 'is_active' => true],
            ['department_code' => 'HHO', 'code' => 'HHOF', 'name' => 'MANAGER OF PASSENGER FACILITY', 'is_active' => true],
            ['department_code' => 'HHE', 'code' => 'HHEC', 'name' => 'MANAGER OF MAINTENANCE TRAIN CREW (ONBOARD)', 'is_active' => true],
            ['department_code' => 'HHE', 'code' => 'HHEO', 'name' => 'MANAGER OF OPERATIONAL', 'is_active' => true],
            ['department_code' => 'HHE', 'code' => 'HHES', 'name' => 'MANAGER OF MAINTENANCE SUPPORT', 'is_active' => true],
            ['department_code' => 'HHE', 'code' => 'HHET', 'name' => 'MANAGER OF TECHNOLOGY AND QUALITY', 'is_active' => true],
            ['department_code' => 'HHF', 'code' => 'HHFI', 'name' => 'MANAGER OF COMPREHENSIVE INSPECTION', 'is_active' => true],
            ['department_code' => 'HHF', 'code' => 'HHFT', 'name' => 'MANAGER OF COMPREHENSIVE TECHNOLOGY OF COMMUNICATION', 'is_active' => true],
            ['department_code' => 'HHF', 'code' => 'HHFL', 'name' => 'MANAGER OF COMPREHENSIVE TECHNOLOGY OF SIGNALING', 'is_active' => true],
            ['department_code' => 'HHF', 'code' => 'HHFO', 'name' => 'MANAGER OF COMPREHENSIVE TECHNOLOGY OF POWER SUPPLY', 'is_active' => true],
            ['department_code' => 'HHF', 'code' => 'HHFB', 'name' => 'MANAGER OF COMPREHENSIVE TECHNOLOGY OF PERMANENT WAY', 'is_active' => true],
            ['department_code' => 'HHF', 'code' => 'HHFD', 'name' => 'MANAGER OF DISPATCHING', 'is_active' => true],
            ['department_code' => 'HHF', 'code' => 'HHFH', 'name' => 'MANAGER OF HEAVY MACHINERY OPERATION AND MAINTENANCE', 'is_active' => true],
            ['department_code' => 'HHF', 'code' => 'HHFC', 'name' => 'MANAGER OF COMMUNICATION MAINTENANCE', 'is_active' => true],
            ['department_code' => 'HHF', 'code' => 'HHFS', 'name' => 'MANAGER OF SIGNALING MAINTENANCE', 'is_active' => true],
            ['department_code' => 'HHF', 'code' => 'HHFW', 'name' => 'MANAGER OF POWER SUPPLY MAINTENANCE', 'is_active' => true],
            ['department_code' => 'HHF', 'code' => 'HHFP', 'name' => 'MANAGER OF PERMANENT WAY AND BUILDING MAINTENANCE', 'is_active' => true]
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
        $bureauHHOP = Bureau::where('code', 'HHOP')->first(); // Operation Bureau (previously HHOO)

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

        // President Director — President Director Directorate
        $presdir = User::firstOrCreate(
            ['email' => 'president@rkap.com'],
            [
                'name' => 'Ir. Budi Karya',
                'password' => Hash::make('password'),
                'directorate_id' => $dirHU->id,
                'position' => 'President Director',
            ]
        );
        $presdir->syncRoles([$rolePresident]);

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
