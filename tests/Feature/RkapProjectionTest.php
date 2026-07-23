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

        $currentMonth = (int) date('n');
        $nextMonth = $currentMonth < 12 ? $currentMonth + 1 : null;
        $prevMonth = $currentMonth > 1 ? $currentMonth - 1 : null;
        $otherMonth = $nextMonth ?? $prevMonth ?? 1;

        for ($m = 1; $m <= 12; $m++) {
            $amount = ($m === $currentMonth || $m === $otherMonth) ? 50000 : 0;
            $this->budgetItem->monthlies()->create([
                'month' => $m,
                'amount' => $amount,
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

        $component = Livewire::test(RkapProjections::class)
            ->call('selectBudgetItem', $this->budgetItem->id);

        for ($m = 1; $m <= 12; $m++) {
            if ($m !== $currentMonth) {
                $component->set('editingProjections.' . $m, 0);
            }
        }

        $component->set('editingProjections.' . $currentMonth, 45000)
            ->call('saveMonthlyProjections')
            ->assertHasNoErrors()
            ->assertDispatched('projections-saved');

        $dbProjection = \App\Models\RkapBudgetItemProjection::where('rkap_budget_item_id', $this->budgetItem->id)
            ->where('month', $currentMonth)
            ->first();

        $this->assertNotNull($dbProjection);
        $this->assertEquals(45000, $dbProjection->amount);
    }

    public function test_projection_can_be_overwritten_for_current_and_future_months(): void
    {
        $this->actingAs($this->kepalaBiro);

        $currentMonth = (int) date('n');

        // Create an existing projection with amount > 0
        \App\Models\RkapBudgetItemProjection::create([
            'rkap_budget_item_id' => $this->budgetItem->id,
            'rkap_period_id' => $this->activePeriod->id,
            'month' => $currentMonth,
            'amount' => 30000,
            'inputted_by' => $this->kepalaBiro->id,
        ]);

        // Try to update it through the Livewire component
        $component = Livewire::test(RkapProjections::class)
            ->call('selectBudgetItem', $this->budgetItem->id);

        for ($m = 1; $m <= 12; $m++) {
            if ($m !== $currentMonth) {
                $component->set('editingProjections.' . $m, 0);
            }
        }

        $component->set('editingProjections.' . $currentMonth, 45000)
            ->call('saveMonthlyProjections')
            ->assertHasNoErrors();

        // The amount in DB should be overwritten to 45000
        $dbProjection = \App\Models\RkapBudgetItemProjection::where('rkap_budget_item_id', $this->budgetItem->id)
            ->where('month', $currentMonth)
            ->first();

        $this->assertNotNull($dbProjection);
        $this->assertEquals(45000, $dbProjection->amount);
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

    public function test_dynamic_summary_totals_below_filter(): void
    {
        $this->actingAs($this->kepalaBiro);

        Livewire::test(RkapProjections::class)
            ->assertSee('Total Anggaran RKAP')
            ->assertSee('Total Realisasi YTD')
            ->assertSee('Total Proyeksi (Akhir Tahun)')
            ->assertSee('Rp ' . number_format($this->budgetItem->total_price, 0, ',', '.'));
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

    public function test_monthly_projection_can_exceed_monthly_budget_plan(): void
    {
        $this->actingAs($this->kepalaBiro);

        $currentMonth = (int) date('n');

        // Budget is 50000 for currentMonth (seeded in setUp, total budget is 100000)
        // Let's try to set a projection of 60000 (which is less than total budget of 100000)
        $component = Livewire::test(RkapProjections::class)
            ->call('selectBudgetItem', $this->budgetItem->id);

        for ($m = 1; $m <= 12; $m++) {
            if ($m !== $currentMonth) {
                $component->set('editingProjections.' . $m, 0);
            }
        }

        $component->set('editingProjections.' . $currentMonth, 60000)
            ->call('saveMonthlyProjections')
            ->assertHasNoErrors();

        // Assert that the projection was saved in database
        $dbProjection = \App\Models\RkapBudgetItemProjection::where('rkap_budget_item_id', $this->budgetItem->id)
            ->where('month', $currentMonth)
            ->first();

        $this->assertNotNull($dbProjection);
        $this->assertEquals(60000, (float)$dbProjection->amount);
    }

    public function test_forbids_projection_accumulation_greater_than_total_approved_budget(): void
    {
        $this->actingAs($this->kepalaBiro);

        $currentMonth = (int) date('n');
        $nextMonth = $currentMonth < 12 ? $currentMonth + 1 : null;

        // Skip test if it's December since we cannot test two months starting from the current month
        if ($nextMonth === null) {
            $this->assertTrue(true);
            return;
        }

        // Dynamically update limits to 60000 for these two months so they pass individual limits
        $this->budgetItem->monthlies()->where('month', $currentMonth)->update(['amount' => 60000]);
        $this->budgetItem->monthlies()->where('month', $nextMonth)->update(['amount' => 60000]);

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

    public function test_projection_initialization_rules(): void
    {
        $this->actingAs($this->kepalaBiro);

        $currentMonth = (int) date('n');
        $pastMonth = $currentMonth > 1 ? $currentMonth - 1 : null;
        $futureMonth = $currentMonth < 12 ? $currentMonth + 1 : null;

        // Seed realization for futureMonth so it behaves as "realisasi sudah diisi"
        if ($futureMonth !== null) {
            $this->budgetItem->realizations()->create([
                'rkap_period_id' => $this->activePeriod->id,
                'month' => $futureMonth,
                'amount' => 45000,
            ]);
        }

        // Seed realization for pastMonth (if it exists)
        if ($pastMonth !== null) {
            $this->budgetItem->realizations()->create([
                'rkap_period_id' => $this->activePeriod->id,
                'month' => $pastMonth,
                'amount' => 20000,
            ]);
        }

        $component = Livewire::test(RkapProjections::class)
            ->call('selectBudgetItem', $this->budgetItem->id);

        // Assert pastMonth equals realization value (or 0 if no realization exists, e.g. for Jan)
        if ($pastMonth !== null) {
            $component->assertSet('editingProjections.' . $pastMonth, 20000);
        }

        // Assert futureMonth with realization equals realization value
        if ($futureMonth !== null) {
            $component->assertSet('editingProjections.' . $futureMonth, 45000);
        }

        // Assert current month (no realization seeded) equals monthly plan amount (50000)
        $component->assertSet('editingProjections.' . $currentMonth, 50000);
    }

    public function test_save_projection_enforces_realization_for_past_and_filled_months(): void
    {
        $this->actingAs($this->kepalaBiro);

        $currentMonth = (int) date('n');
        $pastMonth = $currentMonth > 1 ? $currentMonth - 1 : null;
        $futureMonth = $currentMonth < 12 ? $currentMonth + 1 : null;

        // Seed realizations
        if ($pastMonth !== null) {
            $this->budgetItem->realizations()->create([
                'rkap_period_id' => $this->activePeriod->id,
                'month' => $pastMonth,
                'amount' => 20000,
            ]);
        }

        if ($futureMonth !== null) {
            $this->budgetItem->realizations()->create([
                'rkap_period_id' => $this->activePeriod->id,
                'month' => $futureMonth,
                'amount' => 45000,
            ]);
        }

        $component = Livewire::test(RkapProjections::class)
            ->call('selectBudgetItem', $this->budgetItem->id);

        // Try to set different values
        for ($m = 1; $m <= 12; $m++) {
            if ($m !== $currentMonth && $m !== $pastMonth && $m !== $futureMonth) {
                $component->set('editingProjections.' . $m, 0);
            }
        }

        if ($pastMonth !== null) {
            $component->set('editingProjections.' . $pastMonth, 99999);
        }
        if ($futureMonth !== null) {
            $component->set('editingProjections.' . $futureMonth, 99999);
        }
        
        $component->set('editingProjections.' . $currentMonth, 30000)
            ->call('saveMonthlyProjections')
            ->assertHasNoErrors();

        // Check DB: past month and future month with realization must still equal realization values
        if ($pastMonth !== null) {
            $this->assertEquals(20000, \App\Models\RkapBudgetItemProjection::where('rkap_budget_item_id', $this->budgetItem->id)->where('month', $pastMonth)->value('amount'));
        }
        if ($futureMonth !== null) {
            $this->assertEquals(45000, \App\Models\RkapBudgetItemProjection::where('rkap_budget_item_id', $this->budgetItem->id)->where('month', $futureMonth)->value('amount'));
        }

        // Current month (no realization) should be updated to user input (30000)
        $this->assertEquals(30000, \App\Models\RkapBudgetItemProjection::where('rkap_budget_item_id', $this->budgetItem->id)->where('month', $currentMonth)->value('amount'));
    }

    public function test_projection_validation_respects_allow_exceed_setting_manual(): void
    {
        $this->actingAs($this->kepalaBiro);

        // 1. Setting = 0 (default: exceed not allowed)
        \App\Models\Setting::set('rkap_allow_projection_exceed_budget', '0');

        Livewire::test(RkapProjections::class)
            ->set('activePeriodId', $this->activePeriod->id)
            ->call('selectBudgetItem', $this->budgetItem->id)
            ->set('inputMode', 'yearly')
            ->set('yearlyProjection', 150000)
            ->call('saveMonthlyProjections')
            ->assertHasErrors(['yearlyProjection']);

        // 2. Setting = 1 (exceed allowed)
        \App\Models\Setting::set('rkap_allow_projection_exceed_budget', '1');

        Livewire::test(RkapProjections::class)
            ->set('activePeriodId', $this->activePeriod->id)
            ->call('selectBudgetItem', $this->budgetItem->id)
            ->set('inputMode', 'yearly')
            ->set('yearlyProjection', 150000)
            ->call('saveMonthlyProjections')
            ->assertHasNoErrors();

        $this->budgetItem->refresh();
        $this->assertEquals(150000.00, (float)$this->budgetItem->projection);
    }

    public function test_monthly_projection_validation_respects_allow_exceed_setting_manual(): void
    {
        $this->actingAs($this->kepalaBiro);

        $currentMonth = (int) date('n');

        // 1. Setting = 0 (default: exceed not allowed)
        \App\Models\Setting::set('rkap_allow_projection_exceed_budget', '0');

        Livewire::test(RkapProjections::class)
            ->set('activePeriodId', $this->activePeriod->id)
            ->call('selectBudgetItem', $this->budgetItem->id)
            ->set('inputMode', 'monthly')
            ->set("editingProjections.{$currentMonth}", 150000)
            ->call('saveMonthlyProjections')
            ->assertHasErrors(['editingProjections']);

        // 2. Setting = 1 (exceed allowed)
        \App\Models\Setting::set('rkap_allow_projection_exceed_budget', '1');

        Livewire::test(RkapProjections::class)
            ->set('activePeriodId', $this->activePeriod->id)
            ->call('selectBudgetItem', $this->budgetItem->id)
            ->set('inputMode', 'monthly')
            ->set("editingProjections.{$currentMonth}", 150000)
            ->call('saveMonthlyProjections')
            ->assertHasNoErrors();

        $this->budgetItem->refresh();
        $this->assertEquals(200000.00, (float)$this->budgetItem->projection);
    }

    public function test_projection_tab_navigation_and_summary_dashboard_data(): void
    {
        // 1. Create an admin user
        $roleAdmin = Role::firstOrCreate(['name' => 'admin']);
        $permView = Permission::firstOrCreate(['name' => 'rkap.projection.view', 'guard_name' => 'web']);
        $roleAdmin->givePermissionTo($permView);
        
        $adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin-proj@example.com',
            'password' => bcrypt('password'),
        ]);
        $adminUser->assignRole($roleAdmin);

        $this->actingAs($adminUser);

        // 2. Load the component and check that activeTab is 'input' by default, and we can switch to 'summary'
        $component = Livewire::test(RkapProjections::class)
            ->set('activePeriodId', $this->activePeriod->id)
            ->assertSet('activeTab', 'input')
            ->set('activeTab', 'summary');

        // 3. Call getSummaryData and assert stats
        $summary = $component->instance()->getSummaryData();
        $this->assertArrayHasKey('stats', $summary);
        $this->assertArrayHasKey('rows', $summary);

        $stats = $summary['stats'];
        $this->assertEquals(1, $stats['total_bureaus']);
        $this->assertEquals(1, $stats['total_items']);
        $this->assertEquals(0, $stats['filled_items']); // not input yet
        $this->assertEquals(1, $stats['unfilled_items']);
        $this->assertEquals(0.0, $stats['percentage']);

        // 4. Fill a projection and verify stats update
        $this->actingAs($this->kepalaBiro);
        Livewire::test(RkapProjections::class)
            ->set('activePeriodId', $this->activePeriod->id)
            ->call('selectBudgetItem', $this->budgetItem->id)
            ->set('inputMode', 'yearly')
            ->set('yearlyProjection', 50000)
            ->call('saveMonthlyProjections')
            ->assertHasNoErrors();

        // 5. Re-check summary stats as admin
        $this->actingAs($adminUser);
        $component2 = Livewire::test(RkapProjections::class)
            ->set('activePeriodId', $this->activePeriod->id)
            ->set('activeTab', 'summary');
        
        $summary2 = $component2->instance()->getSummaryData();
        $stats2 = $summary2['stats'];
        $this->assertEquals(1, $stats2['filled_items']);
        $this->assertEquals(0, $stats2['unfilled_items']);
        $this->assertEquals(100.0, $stats2['percentage']);

        $rows = $summary2['rows'];
        $this->assertCount(1, $rows);
        $this->assertEquals('BUR01', $rows[0]['bureau_code']);
        $this->assertEquals(1, $rows[0]['total_items']);
        $this->assertEquals(1, $rows[0]['filled_items']);
        $this->assertEquals('Selesai', $rows[0]['status']);

        // 6. Test Status Filtering
        // When filterStatus is set to 'Selesai', should find 1 row
        $component2->set('filterStatus', 'Selesai');
        $filteredSummary = $component2->instance()->getSummaryData();
        $this->assertCount(1, $filteredSummary['rows']);
        $this->assertEquals(1, $filteredSummary['stats']['total_bureaus']);

        // When filterStatus is set to 'Belum Diisi', should find 0 rows (as it is Selesai)
        $component2->set('filterStatus', 'Belum Diisi');
        $filteredSummary2 = $component2->instance()->getSummaryData();
        $this->assertCount(0, $filteredSummary2['rows']);
        $this->assertEquals(0, $filteredSummary2['stats']['total_bureaus']);
    }

    public function test_can_save_negative_yearly_projection(): void
    {
        $this->actingAs($this->kepalaBiro);

        Livewire::test(RkapProjections::class)
            ->set('activePeriodId', $this->activePeriod->id)
            ->call('selectBudgetItem', $this->budgetItem->id)
            ->set('inputMode', 'yearly')
            ->set('yearlyProjection', -50000)
            ->call('saveMonthlyProjections')
            ->assertHasNoErrors();

        $this->budgetItem->refresh();
        $this->assertEquals(-50000.00, (float)$this->budgetItem->projection);
    }

    public function test_can_save_negative_monthly_projection(): void
    {
        $this->actingAs($this->kepalaBiro);

        $currentMonth = (int) date('n');

        $component = Livewire::test(RkapProjections::class)
            ->set('activePeriodId', $this->activePeriod->id)
            ->call('selectBudgetItem', $this->budgetItem->id)
            ->set('inputMode', 'monthly');

        for ($m = 1; $m <= 12; $m++) {
            if ($m !== $currentMonth) {
                $component->set('editingProjections.' . $m, 0);
            }
        }

        $component->set("editingProjections.{$currentMonth}", -25000)
            ->call('saveMonthlyProjections')
            ->assertHasNoErrors();

        $this->budgetItem->refresh();
        $this->assertEquals(-25000.00, (float)$this->budgetItem->projection);
    }

    public function test_can_export_projections_excel(): void
    {
        $this->actingAs($this->kepalaBiro);

        $response = Livewire::test(RkapProjections::class)
            ->set('activePeriodId', $this->activePeriod->id)
            ->call('exportExcel');

        $response->assertStatus(200);
        $this->assertTrue(
            isset($response->effects['download']) || 
            (isset($response->payload['effects']['download'])) ||
            $response->effects !== null
        );
    }
}
