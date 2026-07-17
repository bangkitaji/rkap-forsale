<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $viewPerm = Permission::firstOrCreate(['name' => 'rkap.transfer.view', 'guard_name' => 'web']);
        $createPerm = Permission::firstOrCreate(['name' => 'rkap.transfer.create', 'guard_name' => 'web']);
        $reviewPerm = Permission::firstOrCreate(['name' => 'rkap.transfer.review', 'guard_name' => 'web']);

        // Roles to assign
        $allRoles = ['admin', 'user', 'kepala_biro', 'kepala_departemen', 'direksi', 'verifikator', 'direktur_utama'];
        foreach ($allRoles as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->givePermissionTo($viewPerm);
                if (in_array($roleName, ['admin', 'user', 'kepala_biro'])) {
                    $role->givePermissionTo($createPerm);
                    $role->givePermissionTo($reviewPerm);
                }
            }
        }
    }

    public function down(): void
    {
        // No rollback action needed, just keep the schema
    }
};
