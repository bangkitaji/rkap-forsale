<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\RkapPeriod;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SummaryDeptPlCapexTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $regularUser;
    protected RkapPeriod $activePeriod;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup Roles and Permissions
        $permSummary = Permission::firstOrCreate(['name' => 'analytics.summary.dept', 'guard_name' => 'web']);
        $roleAdmin = Role::firstOrCreate(['name' => 'admin']);
        $roleAdmin->givePermissionTo($permSummary);

        $roleUser = Role::firstOrCreate(['name' => 'user']);

        // 2. Setup Users
        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin-summary@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->adminUser->assignRole($roleAdmin);

        $this->regularUser = User::create([
            'name' => 'Regular User',
            'email' => 'user-summary@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->regularUser->assignRole($roleUser);

        // 3. Create Period
        $this->activePeriod = RkapPeriod::create([
            'title' => 'RKAP 2026',
            'year' => 2026,
            'status' => 'finalized',
        ]);
    }

    public function test_guest_cannot_access_summary_dept_pl_capex(): void
    {
        $response = $this->get('/analytics/summary-dept/pl-capex');
        $response->assertRedirect('/login');
    }

    public function test_user_without_permission_cannot_access_summary_dept_pl_capex(): void
    {
        $response = $this->actingAs($this->regularUser)->get('/analytics/summary-dept/pl-capex');
        $response->assertStatus(403);
    }

    public function test_admin_can_access_unified_summary_dept_pl_capex(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/analytics/summary-dept/pl-capex');
        $response->assertStatus(200);
        $response->assertSee('Matriks Laba Rugi &amp; Capex per Departemen', false);
        $response->assertSee('LABA RUGI (OPERASIONAL)');
        $response->assertSee('BELANJA MODAL (CAPEX)');
        $response->assertSee('TOTAL KONSOLIDASI CAPEX');
    }

    public function test_old_summary_dept_routes_are_removed(): void
    {
        $this->actingAs($this->adminUser);

        $response1 = $this->get('/analytics/summary-dept/pl');
        $response1->assertStatus(404);

        $response2 = $this->get('/analytics/summary-dept/capex');
        $response2->assertStatus(404);
    }
}
