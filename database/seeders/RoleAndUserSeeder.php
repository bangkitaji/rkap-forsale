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
        if (app()->environment('production')) {
            return;
        }

        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // create permissions
        $permDashboardShow = Permission::firstOrCreate(['name' => 'dashboard.show', 'guard_name' => 'web']);
        $permSettingsShow = Permission::firstOrCreate(['name' => 'settings.show', 'guard_name' => 'web']);
        $permSettingsSatuanManage = Permission::firstOrCreate(['name' => 'settings.satuan.manage', 'guard_name' => 'web']);
        $permMasterDataShow = Permission::firstOrCreate(['name' => 'masterdata.show', 'guard_name' => 'web']);
        $permMasterDataWorkplanView = Permission::firstOrCreate(['name' => 'masterdata.workplan.view', 'guard_name' => 'web']);
        $permMasterDataActivityView = Permission::firstOrCreate(['name' => 'masterdata.activity.view', 'guard_name' => 'web']);
        $permMasterDataWorkplanManage = Permission::firstOrCreate(['name' => 'masterdata.workplan.manage', 'guard_name' => 'web']);
        $permMasterDataActivityManage = Permission::firstOrCreate(['name' => 'masterdata.activity.manage', 'guard_name' => 'web']);
        $permMasterDataCoaManage = Permission::firstOrCreate(['name' => 'masterdata.coa.manage', 'guard_name' => 'web']);
        $permMasterDataCoaGroupManage = Permission::firstOrCreate(['name' => 'masterdata.coagroup.manage', 'guard_name' => 'web']);
        $permRkapShow = Permission::firstOrCreate(['name' => 'rkap.show', 'guard_name' => 'web']);
        $permRkapSubmissionsDept = Permission::firstOrCreate(['name' => 'rkap.submissions.dept', 'guard_name' => 'web']);
        $permRkapCompilationDept = Permission::firstOrCreate(['name' => 'rkap.compilation.dept', 'guard_name' => 'web']);
        $permRkapManagePeriod = Permission::firstOrCreate(['name' => 'rkap.manage.period', 'guard_name' => 'web']);
        $permRkapRealizationUpload = Permission::firstOrCreate(['name' => 'rkap.realization.upload', 'guard_name' => 'web']);
        $permRkapProjectionInput = Permission::firstOrCreate(['name' => 'rkap.projection.input', 'guard_name' => 'web']);
        $permRkapProjectionView = Permission::firstOrCreate(['name' => 'rkap.projection.view', 'guard_name' => 'web']);
        $permRkapReviewPresident = Permission::firstOrCreate(['name' => 'rkap.review.president', 'guard_name' => 'web']);
        $permRkapApprovePresident = Permission::firstOrCreate(['name' => 'rkap.approve.president', 'guard_name' => 'web']);
        $permMasterDataRequestApprove = Permission::firstOrCreate(['name' => 'masterdata.request.approve', 'guard_name' => 'web']);

        $permReportGroupManage = Permission::firstOrCreate(['name' => 'settings.reportgroup.manage', 'guard_name' => 'web']);
        $permUserManagementShow = Permission::firstOrCreate(['name' => 'settings.usermanagement.show', 'guard_name' => 'web']);
        $permOrganizationShow = Permission::firstOrCreate(['name' => 'settings.organization.show', 'guard_name' => 'web']);
        $permSettingsCashflowGroupManage = Permission::firstOrCreate(['name' => 'settings.cashflowgroup.manage', 'guard_name' => 'web']);
        $permSettingsDifferenceGroupManage = Permission::firstOrCreate(['name' => 'settings.differencegroup.manage', 'guard_name' => 'web']);

        // create roles
        $roleAdmin = Role::firstOrCreate(['name' => 'admin']);
        $roleUser = Role::firstOrCreate(['name' => 'user']);
        $roleVerifikator = Role::firstOrCreate(['name' => 'verifikator']);
        $roleDireksi = Role::firstOrCreate(['name' => 'direksi']);
        $rolePresident = Role::firstOrCreate(['name' => 'direktur_utama']);

        // assign permissions to roles
        $roleAdmin->givePermissionTo([
            $permDashboardShow,
            $permSettingsShow,
            $permSettingsSatuanManage,
            $permMasterDataShow,
            $permMasterDataWorkplanView,
            $permMasterDataActivityView,
            $permMasterDataWorkplanManage,
            $permMasterDataActivityManage,
            $permMasterDataCoaManage,
            $permMasterDataCoaGroupManage,
            $permRkapShow,
            $permRkapSubmissionsDept,
            $permRkapCompilationDept,
            $permRkapManagePeriod,
            $permRkapRealizationUpload,
            $permRkapProjectionInput,
            $permRkapProjectionView,
            $permRkapReviewPresident,
            $permRkapApprovePresident,
            $permMasterDataRequestApprove,
            $permReportGroupManage,
            $permUserManagementShow,
            $permOrganizationShow,
            $permSettingsCashflowGroupManage,
            $permSettingsDifferenceGroupManage,
        ]);

        $roleVerifikator->givePermissionTo([
            $permDashboardShow,
            $permRkapShow,
            $permRkapSubmissionsDept,
            $permRkapRealizationUpload,
            $permRkapProjectionInput,
            $permRkapProjectionView,
            $permMasterDataRequestApprove,
            $permSettingsShow,
            $permReportGroupManage,
            $permSettingsCashflowGroupManage,
            $permSettingsDifferenceGroupManage,
            $permMasterDataShow,
            $permMasterDataWorkplanView,
            $permMasterDataActivityView,
            $permMasterDataActivityManage,
        ]);

        $roleDireksi->givePermissionTo([
            $permDashboardShow,
            $permRkapShow,
            $permMasterDataWorkplanView,
            $permMasterDataActivityView,
        ]);


        $rolePresident->givePermissionTo([
            $permDashboardShow,
            $permRkapShow,
            $permRkapReviewPresident,
            $permRkapApprovePresident,
            $permRkapProjectionView,
            $permMasterDataWorkplanView,
            $permMasterDataActivityView,
        ]);

        // Assign rkap.show to all roles
        foreach (Role::all() as $role) {
            $role->givePermissionTo($permRkapShow);
            $role->givePermissionTo($permMasterDataWorkplanView);
            $role->givePermissionTo($permMasterDataActivityView);
        }

        // create admin user
        $admin = User::updateOrCreate(
            ['email' => 'admin@rkap.com'],
            [
                'name' => 'Administrator',
                'password' => Hash::make(config('rkap.seed_default_password', 'password')),
            ]
        );

        $admin->assignRole($roleAdmin);
    }
}
