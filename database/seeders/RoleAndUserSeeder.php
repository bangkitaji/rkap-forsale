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
        $permSettingsShow = Permission::create(['name' => 'settings.show', 'guard_name' => 'web']);

        // create role
        $roleAdmin = Role::create(['name' => 'admin']);
        $roleUser = Role::create(['name' => 'user']);

        // assign permissions to roles
        $roleAdmin->givePermissionTo($permSettingsShow);

        // create admin user
        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin@rkap.com',
            'password' => Hash::make('password'),
        ]);

        $admin->assignRole($roleAdmin);
    }
}
