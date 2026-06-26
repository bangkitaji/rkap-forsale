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

        // Ensure permissions exist
        $permMasterDataShow = Permission::firstOrCreate(['name' => 'masterdata.show', 'guard_name' => 'web']);
        $permMasterDataActivityManage = Permission::firstOrCreate(['name' => 'masterdata.activity.manage', 'guard_name' => 'web']);

        // Give permissions to verifikator role
        $roleVerifikator = Role::where('name', 'verifikator')->first();
        if ($roleVerifikator) {
            $roleVerifikator->givePermissionTo([
                $permMasterDataShow,
                $permMasterDataActivityManage,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revoke permissions from verifikator
        $roleVerifikator = Role::where('name', 'verifikator')->first();
        if ($roleVerifikator) {
            $roleVerifikator->revokePermissionTo([
                'masterdata.show',
                'masterdata.activity.manage',
            ]);
        }
    }
};
