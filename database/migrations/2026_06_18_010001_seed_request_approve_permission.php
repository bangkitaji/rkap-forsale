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

        $perm = Permission::firstOrCreate(['name' => 'masterdata.request.approve', 'guard_name' => 'web']);

        $roleAdmin = Role::where('name', 'admin')->first();
        if ($roleAdmin) {
            $roleAdmin->givePermissionTo($perm);
        }

        $roleVerifikator = Role::where('name', 'verifikator')->first();
        if ($roleVerifikator) {
            $roleVerifikator->givePermissionTo($perm);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
