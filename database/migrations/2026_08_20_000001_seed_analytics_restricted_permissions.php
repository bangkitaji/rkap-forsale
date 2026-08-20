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
        $permCdsView = Permission::firstOrCreate(['name' => 'analytics.cds.view', 'guard_name' => 'web']);
        $permOpeningBalanceManage = Permission::firstOrCreate(['name' => 'analytics.openingbalance.manage', 'guard_name' => 'web']);
        $permBalanceSheetView = Permission::firstOrCreate(['name' => 'analytics.balancesheet.view', 'guard_name' => 'web']);

        $roleAdmin = Role::where('name', 'admin')->first();
        if ($roleAdmin) {
            $roleAdmin->givePermissionTo([
                $permCdsView,
                $permOpeningBalanceManage,
                $permBalanceSheetView,
            ]);
        }

        $roleVerifikator = Role::where('name', 'verifikator')->first();
        if ($roleVerifikator) {
            $roleVerifikator->givePermissionTo([
                $permCdsView,
                $permOpeningBalanceManage,
                $permBalanceSheetView,
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
            'analytics.cds.view',
            'analytics.openingbalance.manage',
            'analytics.balancesheet.view',
        ])->delete();
    }
};
