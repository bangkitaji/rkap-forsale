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
        $permView = Permission::firstOrCreate(['name' => 'rkap.projection.view', 'guard_name' => 'web']);
        $roleKepalaBiro = Role::firstOrCreate(['name' => 'kepala_biro']);
        $roleKepalaBiro->givePermissionTo([$permission, $permView]);
        
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

        for ($m = 1; $m <= 12; $m++) {
            $this->budgetItem->monthlies()->create([
                'month' => $m,
                'amount' => 100000,
            ]);
        }
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

    public function test_verifikator_with_permission_can_access_projection_page(): void
    {
        $roleVerifikator = Role::firstOrCreate(['name' => 'verifikator']);
        $permission = Permission::firstOrCreate(['name' => 'rkap.projection.input', 'guard_name' => 'web']);
        $permView = Permission::firstOrCreate(['name' => 'rkap.projection.view', 'guard_name' => 'web']);
        $roleVerifikator->givePermissionTo([$permission, $permView]);

        $verifikator = User::create([
            'name' => 'Verifikator User',
            'email' => 'verifikator@example.com',
            'password' => bcrypt('password'),
        ]);
        $verifikator->assignRole($roleVerifikator);

        $this->actingAs($verifikator);

        Livewire::test(RkapProjections::class)
            ->assertStatus(200)
            ->assertSee('Input Proyeksi');
    }

    public function test_filter_by_directorate_department_and_bureau(): void
    {
        $roleVerifikator = Role::firstOrCreate(['name' => 'verifikator']);
        $permission = Permission::firstOrCreate(['name' => 'rkap.projection.input', 'guard_name' => 'web']);
        $permView = Permission::firstOrCreate(['name' => 'rkap.projection.view', 'guard_name' => 'web']);
        $roleVerifikator->givePermissionTo([$permission, $permView]);

        $verifikator = User::create([
            'name' => 'Verifikator User 2',
            'email' => 'verifikator2@example.com',
            'password' => bcrypt('password'),
        ]);
        $verifikator->assignRole($roleVerifikator);

        $this->actingAs($verifikator);

        // 1. Initially (no filters), it should show the selection prompt and NOT the budget item
        Livewire::test(RkapProjections::class)
            ->assertSee('Silakan pilih Direktorat, Departemen, atau Biro terlebih dahulu')
            ->assertDontSee('Budget Item Test');

        // 2. Select Directorate
        Livewire::test(RkapProjections::class)
            ->set('directorateId', $this->bureau->department->directorate_id)
            ->assertSee('Budget Item Test')
            ->assertSee('Biro:')
            ->assertSee($this->bureau->code . ' — ' . $this->bureau->name)
            ->assertDontSee('Silakan pilih Direktorat, Departemen, atau Biro terlebih dahulu');

        // 3. Select Department
        Livewire::test(RkapProjections::class)
            ->set('departmentId', $this->bureau->department_id)
            ->assertSee('Budget Item Test')
            ->assertDontSee('Silakan pilih Direktorat, Departemen, atau Biro terlebih dahulu');

        // 4. Select Bureau
        Livewire::test(RkapProjections::class)
            ->set('bureauId', $this->bureau->id)
            ->assertSee('Budget Item Test')
            ->assertDontSee('Silakan pilih Direktorat, Departemen, atau Biro terlebih dahulu');
    }

    public function test_kepala_departemen_can_view_projections_but_cannot_edit(): void
    {
        $roleKepalaDept = Role::firstOrCreate(['name' => 'kepala_departemen']);
        $permView = Permission::firstOrCreate(['name' => 'rkap.projection.view', 'guard_name' => 'web']);
        $roleKepalaDept->givePermissionTo($permView);

        $directorate = Directorate::create([
            'code' => 'DIR02',
            'name' => 'Directorate Test 2',
            'is_active' => true,
        ]);

        $department = Department::create([
            'directorate_id' => $directorate->id,
            'code' => 'DEP02',
            'name' => 'Department Test 2',
            'is_active' => true,
        ]);

        $bureau = Bureau::create([
            'department_id' => $department->id,
            'code' => 'BUR02',
            'name' => 'Bureau Test 2',
            'is_active' => true,
        ]);

        $kepalaDept = User::create([
            'name' => 'Kepala Dept User',
            'email' => 'dept@example.com',
            'password' => bcrypt('password'),
            'bureau_id' => $bureau->id,
            'department_id' => $department->id,
            'directorate_id' => $directorate->id,
        ]);
        $kepalaDept->assignRole($roleKepalaDept);

        // Create an approved submission for this bureau
        $submission = RkapSubmission::create([
            'rkap_period_id' => $this->activePeriod->id,
            'bureau_id' => $bureau->id,
            'created_by' => $kepalaDept->id,
            'status' => 'approved',
            'total_budget' => 50000,
        ]);

        $workPlan = RkapWorkPlan::create([
            'rkap_submission_id' => $submission->id,
            'program_code' => 'PROG02',
            'program_name' => 'Program Test 2',
            'quantity' => 1,
        ]);

        $budgetItem = RkapBudgetItem::create([
            'rkap_work_plan_id' => $workPlan->id,
            'account_code' => 'COA02',
            'description' => 'Budget Item Test 2',
            'quantity' => 1,
            'unit_price' => 50000,
            'total_price' => 50000,
        ]);

        for ($m = 1; $m <= 12; $m++) {
            $budgetItem->monthlies()->create([
                'month' => $m,
                'amount' => 50000,
            ]);
        }

        $this->actingAs($kepalaDept);

        // 1. Can view page and see list
        Livewire::test(RkapProjections::class)
            ->assertStatus(200)
            ->assertSee('Budget Item Test 2')
            // Assert that the edit button is not present
            ->assertDontSee('wire:click="selectBudgetItem');

        // 2. Calling selectBudgetItem directly should fail with 403
        Livewire::test(RkapProjections::class)
            ->call('selectBudgetItem', $budgetItem->id)
            ->assertStatus(403);
    }

    public function test_forbids_projection_amount_greater_than_monthly_budget_plan(): void
    {
        $this->actingAs($this->kepalaBiro);

        $currentMonth = (int) date('n');

        // Budget is 100000 for currentMonth (seeded in setUp)
        // Let's try to set a projection of 120000
        Livewire::test(RkapProjections::class)
            ->call('selectBudgetItem', $this->budgetItem->id)
            ->set('editingProjections.' . $currentMonth, 120000)
            ->call('saveMonthlyProjections')
            ->assertHasErrors(['editingProjections.' . $currentMonth]);

        // Assert that the projection was NOT saved in database
        $dbProjection = \App\Models\RkapBudgetItemProjection::where('rkap_budget_item_id', $this->budgetItem->id)
            ->where('month', $currentMonth)
            ->first();

        $this->assertNull($dbProjection);
    }

    public function test_forbids_projection_accumulation_greater_than_total_approved_budget(): void
    {
        $this->actingAs($this->kepalaBiro);

        // budgetItem total_price is 100000. Let's make individual months fit under monthly budgets,
        // but let their sum exceed 100000.
        // Let's set Month 1 = 60000, Month 2 = 60000 (Sum = 120000 > 100000).
        // (Monthly limits are 100000 each, so individually they are valid, but combined they exceed 100000).
        
        $currentMonth = (int) date('n');
        $nextMonth = $currentMonth < 12 ? $currentMonth + 1 : null;

        // Skip test if it's December since we cannot test two months starting from the current month
        if ($nextMonth === null) {
            $this->assertTrue(true);
            return;
        }

        Livewire::test(RkapProjections::class)
            ->call('selectBudgetItem', $this->budgetItem->id)
            ->set('editingProjections.' . $currentMonth, 60000)
            ->set('editingProjections.' . $nextMonth, 60000)
            ->call('saveMonthlyProjections')
            ->assertHasErrors(['editingProjections']);

        // Assert that nothing was saved
        $savedProjectionsCount = \App\Models\RkapBudgetItemProjection::where('rkap_budget_item_id', $this->budgetItem->id)->count();
        $this->assertEquals(0, $savedProjectionsCount);
    }

    public function test_save_yearly_projection_separately_without_monthly_distribution(): void
    {
        $this->actingAs($this->kepalaBiro);

        Livewire::test(RkapProjections::class)
            ->call('selectBudgetItem', $this->budgetItem->id)
            ->set('inputMode', 'yearly')
            ->set('yearlyProjection', 85000)
            ->call('saveMonthlyProjections')
            ->assertHasNoErrors()
            ->assertDispatched('projections-saved');

        // Check budget item projection column directly
        $this->budgetItem->refresh();
        $this->assertEquals(85000, (float)$this->budgetItem->projection);

        // Check that monthly projections are empty/deleted
        $savedProjectionsCount = \App\Models\RkapBudgetItemProjection::where('rkap_budget_item_id', $this->budgetItem->id)->count();
        $this->assertEquals(0, $savedProjectionsCount);
    }

    public function test_cannot_save_yearly_projection_exceeding_total_price(): void
    {
        $this->actingAs($this->kepalaBiro);

        Livewire::test(RkapProjections::class)
            ->call('selectBudgetItem', $this->budgetItem->id)
            ->set('inputMode', 'yearly')
            ->set('yearlyProjection', 120000) // Budget is 100000
            ->call('saveMonthlyProjections')
            ->assertHasErrors(['yearlyProjection']);

        // Check budget item projection is not updated
        $this->budgetItem->refresh();
        $this->assertEquals(0, (float)$this->budgetItem->projection);
    }

    public function test_mode_locked_to_yearly_if_yearly_projection_exists(): void
    {
        $this->actingAs($this->kepalaBiro);

        // Seed yearly projection in database
        $this->budgetItem->update(['projection' => 50000]);

        Livewire::test(RkapProjections::class)
            ->call('selectBudgetItem', $this->budgetItem->id)
            ->assertSet('inputMode', 'yearly')
            ->assertSet('yearlyProjection', 50000)
            ->assertSet('modeLocked', true);
    }

    public function test_mode_locked_to_monthly_if_monthly_projections_exist(): void
    {
        $this->actingAs($this->kepalaBiro);

        // Seed monthly projection
        \App\Models\RkapBudgetItemProjection::create([
            'rkap_budget_item_id' => $this->budgetItem->id,
            'rkap_period_id' => $this->activePeriod->id,
            'month' => 5,
            'amount' => 30000,
            'inputted_by' => $this->kepalaBiro->id,
        ]);
        $this->budgetItem->update(['projection' => 30000]);

        Livewire::test(RkapProjections::class)
            ->call('selectBudgetItem', $this->budgetItem->id)
            ->assertSet('inputMode', 'monthly')
            ->assertSet('yearlyProjection', 30000)
            ->assertSet('modeLocked', true);
    }
}
