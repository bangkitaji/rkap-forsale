<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RoleAndUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // create permissions
        $permDashboardShow = Permission::firstOrCreate(['name' => 'dashboard.show', 'guard_name' => 'web']);
        $permSettingsShow = Permission::firstOrCreate(['name' => 'settings.show', 'guard_name' => 'web']);
        $permSettingsSatuanManage = Permission::firstOrCreate(['name' => 'settings.satuan.manage', 'guard_name' => 'web']);
        $permMasterDataShow = Permission::firstOrCreate(['name' => 'masterdata.show', 'guard_name' => 'web']);
        $permMasterDataWorkplanManage = Permission::firstOrCreate(['name' => 'masterdata.workplan.manage', 'guard_name' => 'web']);
        $permMasterDataActivityManage = Permission::firstOrCreate(['name' => 'masterdata.activity.manage', 'guard_name' => 'web']);
        $permMasterDataCoaManage = Permission::firstOrCreate(['name' => 'masterdata.coa.manage', 'guard_name' => 'web']);
        $permRkapShow = Permission::firstOrCreate(['name' => 'rkap.show', 'guard_name' => 'web']);
        $permRkapSubmissionsDept = Permission::firstOrCreate(['name' => 'rkap.submissions.dept', 'guard_name' => 'web']);



        // create role
        $roleAdmin = Role::firstOrCreate(['name' => 'admin']);
        $roleUser = Role::firstOrCreate(['name' => 'user']);

        // assign permissions to roles
        $roleAdmin->givePermissionTo([
            $permDashboardShow,
            $permSettingsShow,
            $permSettingsSatuanManage,
            $permMasterDataShow,
            $permMasterDataWorkplanManage,
            $permMasterDataActivityManage,
            $permMasterDataCoaManage,
            $permRkapShow,
            $permRkapSubmissionsDept,
        ]);

        // create admin user
        $admin = User::updateOrCreate(
            ['email' => 'admin@rkap.com'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('password'),
            ]
        );

        $admin->assignRole($roleAdmin);
    }
}
