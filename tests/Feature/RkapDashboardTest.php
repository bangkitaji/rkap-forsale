<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\RkapPeriod;
use App\Models\Bureau;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\User;
use App\Models\RkapSubmission;
use App\Models\RkapWorkPlan;
use App\Models\RkapBudgetItem;
use App\Models\RkapBudgetItemRealization;
use App\Models\RkapBudgetItemProjection;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RkapDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $kabiro1;
    protected User $kabiro2;
    protected RkapPeriod $period;
    protected Bureau $bureau1;
    protected Bureau $bureau2;
    protected RkapSubmission $submission1;
    protected RkapSubmission $submission2;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Roles & Permissions setup
        Role::firstOrCreate(['name' => 'admin']);
        
        $roleKabiro = Role::firstOrCreate(['name' => 'kepala_biro']);
        $roleKabiro->givePermissionTo(Permission::firstOrCreate(['name' => 'rkap.show', 'guard_name' => 'web']));

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->admin->assignRole('admin');

        // 2. Setup active Period
        $this->period = RkapPeriod::create([
            'year' => (int) date('Y'),
            'title' => 'RKAP ' . date('Y'),
            'status' => 'finalized',
            'submission_start' => now()->subDay(),
            'submission_end' => now()->addDay(),
        ]);

        // 3. Organization Setup
        $directorate = Directorate::create(['code' => 'D1', 'name' => 'Dir 1']);
        $department = Department::create(['directorate_id' => $directorate->id, 'code' => 'DP1', 'name' => 'Dept 1']);
        
        $this->bureau1 = Bureau::create(['department_id' => $department->id, 'code' => 'B1', 'name' => 'Bur 1']);
        $this->bureau2 = Bureau::create(['department_id' => $department->id, 'code' => 'B2', 'name' => 'Bur 2']);

        $this->kabiro1 = User::create([
            'name' => 'Kabiro 1',
            'email' => 'kabiro1@example.com',
            'password' => bcrypt('password'),
            'bureau_id' => $this->bureau1->id,
        ]);
        $this->kabiro1->assignRole($roleKabiro);

        $this->kabiro2 = User::create([
            'name' => 'Kabiro 2',
            'email' => 'kabiro2@example.com',
            'password' => bcrypt('password'),
            'bureau_id' => $this->bureau2->id,
        ]);
        $this->kabiro2->assignRole($roleKabiro);

        // 4. Submissions & Budgets
        $this->submission1 = RkapSubmission::create([
            'rkap_period_id' => $this->period->id,
            'bureau_id' => $this->bureau1->id,
            'created_by' => $this->kabiro1->id,
            'status' => 'approved',
            'total_budget' => 60000,
        ]);

        $this->submission2 = RkapSubmission::create([
            'rkap_period_id' => $this->period->id,
            'bureau_id' => $this->bureau2->id,
            'created_by' => $this->kabiro2->id,
            'status' => 'approved',
            'total_budget' => 40000,
        ]);

        // Mock work plans and items
        $wp1 = RkapWorkPlan::create([
            'rkap_submission_id' => $this->submission1->id,
            'program_code' => 'WP1',
            'program_name' => 'WP1',
        ]);
        $bi1 = RkapBudgetItem::create([
            'rkap_work_plan_id' => $wp1->id,
            'account_code' => '521111',
            'description' => 'Item 1',
            'quantity' => 1,
            'unit_price' => 60000,
        ]);

        $wp2 = RkapWorkPlan::create([
            'rkap_submission_id' => $this->submission2->id,
            'program_code' => 'WP2',
            'program_name' => 'WP2',
        ]);
        $bi2 = RkapBudgetItem::create([
            'rkap_work_plan_id' => $wp2->id,
            'account_code' => '522222',
            'description' => 'Item 2',
            'quantity' => 1,
            'unit_price' => 40000,
        ]);

        // Add Realization & Projection for Bureau 1
        RkapBudgetItemRealization::create([
            'rkap_budget_item_id' => $bi1->id,
            'rkap_period_id' => $this->period->id,
            'month' => 3,
            'amount' => 15000,
            'uploaded_by' => $this->admin->id,
            'uploaded_at' => now(),
        ]);
        RkapBudgetItemProjection::create([
            'rkap_budget_item_id' => $bi1->id,
            'rkap_period_id' => $this->period->id,
            'month' => 12,
            'amount' => 50000,
            'inputted_by' => $this->kabiro1->id,
        ]);

        // Add Realization & Projection for Bureau 2
        RkapBudgetItemRealization::create([
            'rkap_budget_item_id' => $bi2->id,
            'rkap_period_id' => $this->period->id,
            'month' => 4,
            'amount' => 10000,
            'uploaded_by' => $this->admin->id,
            'uploaded_at' => now(),
        ]);
        RkapBudgetItemProjection::create([
            'rkap_budget_item_id' => $bi2->id,
            'rkap_period_id' => $this->period->id,
            'month' => 12,
            'amount' => 38000,
            'inputted_by' => $this->kabiro2->id,
        ]);
    }

    public function test_guest_is_redirected(): void
    {
        $response = $this->get('/analytics');
        $response->assertRedirect('/login');
    }

    public function test_admin_sees_aggregated_statistics(): void
    {
        $response = $this->actingAs($this->admin)->get('/analytics');

        $response->assertStatus(200);
        $response->assertViewHas('activePeriod');
        $response->assertViewHas('stats');

        $stats = $response->viewData('stats');
        
        // Sum of both submission budgets (60000 + 40000)
        $this->assertEquals(100000.0, $stats['total_budget']);
        
        // Sum of both realizations (15000 + 10000)
        $this->assertEquals(25000.0, $stats['total_realization']);
        
        // Sum of both projections (50000 + 38000)
        $this->assertEquals(88000.0, $stats['total_projection']);

        // Absorption Rate: (25000 / 100000) * 100 = 25%
        $this->assertEquals(25.0, $stats['absorption_rate']);

        // Outlook Rate: (88000 / 100000) * 100 = 88%
        $this->assertEquals(88.0, $stats['outlook_rate']);
    }

    public function test_kabiro_scoping_limits_data(): void
    {
        $response = $this->actingAs($this->kabiro1)->get('/analytics');

        $response->assertStatus(200);
        $stats = $response->viewData('stats');

        // Only Bureau 1 budget (60000)
        $this->assertEquals(60000.0, $stats['total_budget']);
        
        // Only Bureau 1 realization (15000)
        $this->assertEquals(15000.0, $stats['total_realization']);
        
        // Only Bureau 1 projection (50000)
        $this->assertEquals(50000.0, $stats['total_projection']);

        // Absorption Rate: (15000 / 60000) * 100 = 25%
        $this->assertEquals(25.0, $stats['absorption_rate']);

        // Outlook Rate: (50000 / 60000) * 100 = 83.3%
        $this->assertEquals(83.3, $stats['outlook_rate']);
    }
}
