<?php

namespace Tests\Feature;

use Tests\TestCase;
use Livewire\Livewire;
use App\Livewire\Rkap\RkapProjections;
use App\Models\RkapPeriod;
use App\Models\Bureau;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\User;
use App\Models\RkapSubmission;
use App\Models\RkapWorkPlan;
use App\Models\RkapBudgetItem;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RkapProjectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $kepalaBiro;
    protected User $regularUser;
    protected RkapPeriod $prevPeriod;
    protected RkapPeriod $activePeriod;
    protected Bureau $bureau;
    protected RkapSubmission $approvedSubmission;
    protected RkapBudgetItem $budgetItem;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create Roles and Permissions
        $permission = Permission::firstOrCreate(['name' => 'rkap.projection.input', 'guard_name' => 'web']);
        $roleKepalaBiro = Role::firstOrCreate(['name' => 'kepala_biro']);
        $roleKepalaBiro->givePermissionTo($permission);
        
        $roleUser = Role::firstOrCreate(['name' => 'user']);

        // 2. Setup organization
        $directorate = Directorate::create([
            'code' => 'DIR01',
            'name' => 'Directorate Test',
            'is_active' => true,
        ]);

        $department = Department::create([
            'directorate_id' => $directorate->id,
            'code' => 'DEP01',
            'name' => 'Department Test',
            'is_active' => true,
        ]);

        $this->bureau = Bureau::create([
            'department_id' => $department->id,
            'code' => 'BUR01',
            'name' => 'Bureau Test',
            'is_active' => true,
        ]);

        // 3. Create Users
        $this->kepalaBiro = User::create([
            'name' => 'Biro User',
            'email' => 'biro@example.com',
            'password' => bcrypt('password'),
            'bureau_id' => $this->bureau->id,
            'department_id' => $department->id,
            'directorate_id' => $directorate->id,
        ]);
        $this->kepalaBiro->assignRole($roleKepalaBiro);

        $this->regularUser = User::create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->regularUser->assignRole($roleUser);

        // 4. Periods (Previous and Active)
        $this->prevPeriod = RkapPeriod::create([
            'year' => 2025,
            'title' => 'RKAP 2025',
            'status' => 'closed',
        ]);

        $this->activePeriod = RkapPeriod::create([
            'year' => (int) date('Y'),
            'title' => 'RKAP ' . date('Y'),
            'status' => 'finalized',
            'submission_start' => now()->subDay(),
            'submission_end' => now()->addDay(),
        ]);

        // 5. Approved Submission in active period (current year finalized RKAP)
        $this->approvedSubmission = RkapSubmission::create([
            'rkap_period_id' => $this->activePeriod->id,
            'bureau_id' => $this->bureau->id,
            'created_by' => $this->kepalaBiro->id,
            'status' => 'approved',
            'total_budget' => 100000,
        ]);

        // 6. Work Plan & Budget Item
        $workPlan = RkapWorkPlan::create([
            'rkap_submission_id' => $this->approvedSubmission->id,
            'program_code' => 'PROG01',
            'program_name' => 'Program Test',
            'quantity' => 1,
        ]);

        $this->budgetItem = RkapBudgetItem::create([
            'rkap_work_plan_id' => $workPlan->id,
            'account_code' => 'COA01',
            'description' => 'Budget Item Test',
            'quantity' => 1,
            'unit_price' => 100000,
            'total_price' => 100000,
        ]);
    }

    public function test_user_without_permission_cannot_access_projection_page(): void
    {
        $this->actingAs($this->regularUser);

        Livewire::test(RkapProjections::class)
            ->assertStatus(403);
    }

    public function test_kepala_biro_with_permission_can_access_projection_page(): void
    {
        $this->actingAs($this->kepalaBiro);

        Livewire::test(RkapProjections::class)
            ->assertStatus(200)
            ->assertSee('Input Proyeksi')
            ->assertSee('Budget Item Test');
    }

    public function test_kepala_biro_can_input_and_save_projections(): void
    {
        $this->actingAs($this->kepalaBiro);

        $currentMonth = (int) date('n');

        Livewire::test(RkapProjections::class)
            ->call('selectBudgetItem', $this->budgetItem->id)
            ->set('editingProjections.' . $currentMonth, 85000)
            ->call('saveMonthlyProjections')
            ->assertHasNoErrors()
            ->assertDispatched('projections-saved');

        $dbProjection = \App\Models\RkapBudgetItemProjection::where('rkap_budget_item_id', $this->budgetItem->id)
            ->where('month', $currentMonth)
            ->first();

        $this->assertNotNull($dbProjection);
        $this->assertEquals(85000, $dbProjection->amount);
    }

    public function test_projection_cannot_be_overwritten_if_already_exists(): void
    {
        $this->actingAs($this->kepalaBiro);

        $currentMonth = (int) date('n');

        // Create an existing projection with amount > 0
        \App\Models\RkapBudgetItemProjection::create([
            'rkap_budget_item_id' => $this->budgetItem->id,
            'rkap_period_id' => $this->activePeriod->id,
            'month' => $currentMonth,
            'amount' => 50000,
            'inputted_by' => $this->kepalaBiro->id,
        ]);

        // Try to update it through the Livewire component
        Livewire::test(RkapProjections::class)
            ->call('selectBudgetItem', $this->budgetItem->id)
            ->set('editingProjections.' . $currentMonth, 75000)
            ->call('saveMonthlyProjections')
            ->assertHasNoErrors();

        // The amount in DB should still be 50000 (not overwritten)
        $dbProjection = \App\Models\RkapBudgetItemProjection::where('rkap_budget_item_id', $this->budgetItem->id)
            ->where('month', $currentMonth)
            ->first();

        $this->assertNotNull($dbProjection);
        $this->assertEquals(50000, $dbProjection->amount);
    }
}
