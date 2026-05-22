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
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // ── 3. Assign Permissions to Roles ──
        $roleAdmin->syncPermissions(Permission::all());

        $roleKepalaBiro->syncPermissions([
            'rkap.create', 'rkap.edit', 'rkap.delete', 'rkap.submit', 'rkap.view.own', 'rkap.comment',
        ]);

        $roleKepalaDept->syncPermissions([
            'rkap.view.dept', 'rkap.review.dept', 'rkap.approve.dept', 'rkap.comment',
        ]);

        $roleDireksi->syncPermissions([
            'rkap.view.dept', 'rkap.review.dir', 'rkap.approve.dir', 'rkap.comment',
        ]);

        $roleVerifikator->syncPermissions([
            'rkap.view.all', 'rkap.review.final', 'rkap.approve.final', 'rkap.comment',
        ]);

        // ── 4. Sample Organization ──
        $dirTeknik = Directorate::firstOrCreate(
            ['code' => 'DIR-TK'],
            ['name' => 'Direktorat Teknik', 'is_active' => true]
        );
        $dirKeuangan = Directorate::firstOrCreate(
            ['code' => 'DIR-KU'],
            ['name' => 'Direktorat Keuangan & Umum', 'is_active' => true]
        );

        $deptIT = Department::firstOrCreate(
            ['code' => 'DEPT-IT'],
            ['directorate_id' => $dirTeknik->id, 'name' => 'Departemen Teknologi Informasi', 'is_verifier' => false, 'is_active' => true]
        );
        $deptOps = Department::firstOrCreate(
            ['code' => 'DEPT-OP'],
            ['directorate_id' => $dirTeknik->id, 'name' => 'Departemen Operasional', 'is_verifier' => false, 'is_active' => true]
        );
        $deptKeu = Department::firstOrCreate(
            ['code' => 'DEPT-KU'],
            ['directorate_id' => $dirKeuangan->id, 'name' => 'Departemen Keuangan', 'is_verifier' => false, 'is_active' => true]
        );
        $deptRen = Department::firstOrCreate(
            ['code' => 'DEPT-RN'],
            ['directorate_id' => $dirKeuangan->id, 'name' => 'Departemen Perencanaan & Anggaran', 'is_verifier' => true, 'is_active' => true]
        );

        $bureauInfra = Bureau::firstOrCreate(
            ['code' => 'BRO-INF'],
            ['department_id' => $deptIT->id, 'name' => 'Biro Infrastruktur', 'is_active' => true]
        );
        $bureauDev = Bureau::firstOrCreate(
            ['code' => 'BRO-DEV'],
            ['department_id' => $deptIT->id, 'name' => 'Biro Pengembangan Sistem', 'is_active' => true]
        );
        $bureauOps = Bureau::firstOrCreate(
            ['code' => 'BRO-OPS'],
            ['department_id' => $deptOps->id, 'name' => 'Biro Operasional Lapangan', 'is_active' => true]
        );
        $bureauMaint = Bureau::firstOrCreate(
            ['code' => 'BRO-MNT'],
            ['department_id' => $deptOps->id, 'name' => 'Biro Pemeliharaan', 'is_active' => true]
        );
        $bureauAkun = Bureau::firstOrCreate(
            ['code' => 'BRO-AKN'],
            ['department_id' => $deptKeu->id, 'name' => 'Biro Akuntansi', 'is_active' => true]
        );
        $bureauAngg = Bureau::firstOrCreate(
            ['code' => 'BRO-ANG'],
            ['department_id' => $deptRen->id, 'name' => 'Biro Anggaran', 'is_active' => true]
        );

        // ── 5. Sample Users ──
        // Admin (already created by RoleAndUserSeeder, just update org if needed)
        $admin = User::where('email', 'admin@rkap.com')->first();
        if ($admin) {
            $admin->update(['directorate_id' => $dirKeuangan->id, 'position' => 'Administrator Sistem']);
        }

        // Kepala Biro — Biro Infrastruktur
        $kb1 = User::firstOrCreate(
            ['email' => 'kabiro.infra@rkap.com'],
            [
                'name' => 'Budi Santoso',
                'password' => Hash::make('password'),
                'bureau_id' => $bureauInfra->id,
                'department_id' => $deptIT->id,
                'directorate_id' => $dirTeknik->id,
                'position' => 'Kepala Biro Infrastruktur',
            ]
        );
        $kb1->syncRoles([$roleKepalaBiro]);

        // Kepala Biro — Biro Pengembangan Sistem
        $kb2 = User::firstOrCreate(
            ['email' => 'kabiro.dev@rkap.com'],
            [
                'name' => 'Siti Rahayu',
                'password' => Hash::make('password'),
                'bureau_id' => $bureauDev->id,
                'department_id' => $deptIT->id,
                'directorate_id' => $dirTeknik->id,
                'position' => 'Kepala Biro Pengembangan Sistem',
            ]
        );
        $kb2->syncRoles([$roleKepalaBiro]);

        // Kepala Departemen — Dept IT
        $kd1 = User::firstOrCreate(
            ['email' => 'kadept.it@rkap.com'],
            [
                'name' => 'Andi Wijaya',
                'password' => Hash::make('password'),
                'department_id' => $deptIT->id,
                'directorate_id' => $dirTeknik->id,
                'position' => 'Kepala Departemen TI',
            ]
        );
        $kd1->syncRoles([$roleKepalaDept]);

        // Kepala Departemen — Dept Operasional
        $kd2 = User::firstOrCreate(
            ['email' => 'kadept.ops@rkap.com'],
            [
                'name' => 'Rini Kusuma',
                'password' => Hash::make('password'),
                'department_id' => $deptOps->id,
                'directorate_id' => $dirTeknik->id,
                'position' => 'Kepala Departemen Operasional',
            ]
        );
        $kd2->syncRoles([$roleKepalaDept]);

        // Direksi — Direktorat Teknik
        $dir1 = User::firstOrCreate(
            ['email' => 'direksi.teknik@rkap.com'],
            [
                'name' => 'Dr. Hendra Gunawan',
                'password' => Hash::make('password'),
                'directorate_id' => $dirTeknik->id,
                'position' => 'Direktur Teknik',
            ]
        );
        $dir1->syncRoles([$roleDireksi]);

        // Direksi — Direktorat Keuangan
        $dir2 = User::firstOrCreate(
            ['email' => 'direksi.keuangan@rkap.com'],
            [
                'name' => 'Ir. Dewi Purnama',
                'password' => Hash::make('password'),
                'directorate_id' => $dirKeuangan->id,
                'position' => 'Direktur Keuangan & Umum',
            ]
        );
        $dir2->syncRoles([$roleDireksi]);

        // Verifikator — Dept Perencanaan & Anggaran
        $ver = User::firstOrCreate(
            ['email' => 'verifikator@rkap.com'],
            [
                'name' => 'Ahmad Fauzi',
                'password' => Hash::make('password'),
                'department_id' => $deptRen->id,
                'directorate_id' => $dirKeuangan->id,
                'position' => 'Kepala Departemen Perencanaan & Anggaran',
            ]
        );
        $ver->syncRoles([$roleVerifikator]);

        $this->command->info('RKAP Seeder completed: roles, permissions, org structure, and sample users created.');
    }
}
