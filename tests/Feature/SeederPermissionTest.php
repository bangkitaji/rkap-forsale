<?php

namespace Tests\Feature;

use Tests\TestCase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\RoleAndUserSeeder;
use Database\Seeders\RkapSeeder;

class SeederPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_roles_have_rkap_show_permission_after_seeding(): void
    {
        // 1. Run seeders
        $this->seed(RoleAndUserSeeder::class);
        $this->seed(RkapSeeder::class);

        // 2. Fetch all roles
        $roles = Role::all();
        $this->assertNotEmpty($roles, 'No roles were seeded.');

        // 3. Assert each role has 'rkap.show' permission
        foreach ($roles as $role) {
            $this->assertTrue(
                $role->hasPermissionTo('rkap.show'),
                "Role '{$role->name}' does not have the 'rkap.show' permission."
            );
        }
    }
}
