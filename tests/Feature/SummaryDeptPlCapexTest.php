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
    protected \App\Models\Department $department;

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

        // 4. Create Directorate and Department
        $dir = \App\Models\Directorate::create(['code' => 'DIR_TEST', 'name' => 'Test Directorate']);
        $this->department = \App\Models\Department::create(['code' => 'DEPT_TEST', 'name' => 'Test Department', 'directorate_id' => $dir->id]);
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

    public function test_admin_can_compare_different_periods_unified_summary(): void
    {
        $period2027 = RkapPeriod::create([
            'title' => 'RKAP 2027',
            'year' => 2027,
            'status' => 'finalized',
        ]);

        $response = $this->actingAs($this->adminUser)->get("/analytics/summary-dept/pl-capex?budget_period_id={$period2027->id}&realization_period_id={$this->activePeriod->id}&projection_period_id={$this->activePeriod->id}");
        $response->assertStatus(200);
        $response->assertSee('Matriks Laba Rugi &amp; Capex per Departemen', false);
        $response->assertSee('LABA RUGI (OPERASIONAL)');
        $response->assertSee('Periode Anggaran (B)');
        $response->assertSee('Periode Realisasi (R)');
        $response->assertSee('Periode Proyeksi (P)');
        $response->assertSee('B (2027)');
        $response->assertSee('R (2026)');
        $response->assertSee('P (2026)');
    }

    public function test_old_summary_dept_routes_are_removed(): void
    {
        $this->actingAs($this->adminUser);

        $response1 = $this->get('/analytics/summary-dept/pl');
        $response1->assertStatus(404);

        $response2 = $this->get('/analytics/summary-dept/capex');
        $response2->assertStatus(404);
    }

    public function test_guest_cannot_access_summary_dept_detail(): void
    {
        $response = $this->get('/analytics/summary-dept/detail');
        $response->assertRedirect('/login');
    }

    public function test_user_without_permission_cannot_access_summary_dept_detail(): void
    {
        $response = $this->actingAs($this->regularUser)->get("/analytics/summary-dept/detail?period_id={$this->activePeriod->id}&department_id={$this->department->id}&report_group_id=capex");
        $response->assertStatus(403);
    }

    public function test_admin_can_access_summary_dept_detail(): void
    {
        $response = $this->actingAs($this->adminUser)->get("/analytics/summary-dept/detail?period_id={$this->activePeriod->id}&department_id={$this->department->id}&report_group_id=capex");
        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }
}
