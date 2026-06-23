<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create new permissions
        $permReportGroupManage = Permission::firstOrCreate(['name' => 'settings.reportgroup.manage', 'guard_name' => 'web']);
        $permUserManagementShow = Permission::firstOrCreate(['name' => 'settings.usermanagement.show', 'guard_name' => 'web']);
        $permOrganizationShow = Permission::firstOrCreate(['name' => 'settings.organization.show', 'guard_name' => 'web']);

        $roleAdmin = Role::where('name', 'admin')->first();
        if ($roleAdmin) {
            $roleAdmin->givePermissionTo([
                $permReportGroupManage,
                $permUserManagementShow,
                $permOrganizationShow,
            ]);
        }

        $roleVerifikator = Role::where('name', 'verifikator')->first();
        if ($roleVerifikator) {
            // Verifikator gets settings.show so they can see the settings section
            $permSettingsShow = Permission::firstOrCreate(['name' => 'settings.show', 'guard_name' => 'web']);
            $roleVerifikator->givePermissionTo([
                $permSettingsShow,
                $permReportGroupManage,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove permissions
        Permission::whereIn('name', [
            'settings.reportgroup.manage',
            'settings.usermanagement.show',
            'settings.organization.show'
        ])->delete();
    }
};
