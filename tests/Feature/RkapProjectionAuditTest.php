<?php

namespace Tests\Feature;

use Tests\TestCase;
use Livewire\Livewire;
use App\Livewire\Rkap\RkapProjections;
use App\Livewire\Rkap\RkapProjectionUpload;
use App\Models\RkapPeriod;
use App\Models\Bureau;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\User;
use App\Models\RkapSubmission;
use App\Models\RkapWorkPlan;
use App\Models\RkapBudgetItem;
use App\Models\RkapProjectionLog;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RkapProjectionAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected RkapPeriod $activePeriod;
    protected Bureau $bureau;
    protected RkapSubmission $submission;
    protected RkapBudgetItem $budgetItem;

    protected function setUp(): void
    {
        parent::setUp();

        $permInput = Permission::firstOrCreate(['name' => 'rkap.projection.input', 'guard_name' => 'web']);
        $permView  = Permission::firstOrCreate(['name' => 'rkap.projection.view', 'guard_name' => 'web']);

        $roleAdmin = Role::firstOrCreate(['name' => 'admin']);
        $roleAdmin->givePermissionTo([$permInput, $permView]);

        $roleBiro = Role::firstOrCreate(['name' => 'kepala_biro']);
        $roleBiro->givePermissionTo([$permInput, $permView]);

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

        $this->user = User::create([
            'name' => 'Audit Tester',
            'email' => 'audit@example.com',
            'password' => bcrypt('password'),
            'bureau_id' => $this->bureau->id,
            'department_id' => $department->id,
            'directorate_id' => $directorate->id,
        ]);
        $this->user->assignRole('kepala_biro');

        $this->activePeriod = RkapPeriod::create([
            'year' => (int) date('Y'),
            'title' => 'RKAP ' . date('Y'),
            'status' => 'finalized',
        ]);

        $this->submission = RkapSubmission::create([
            'rkap_period_id' => $this->activePeriod->id,
            'bureau_id' => $this->bureau->id,
            'created_by' => $this->user->id,
            'status' => 'approved',
            'total_budget' => 10000000,
        ]);

        $workPlan = RkapWorkPlan::create([
            'rkap_submission_id' => $this->submission->id,
            'program_name' => 'Test Audit Program',
        ]);

        $this->budgetItem = RkapBudgetItem::create([
            'rkap_work_plan_id' => $workPlan->id,
            'account_code' => '5101001',
            'description' => 'Gaji & Tunjangan',
            'quantity' => 1,
            'unit' => 'Bulan',
            'unit_price' => 10000000,
            'total_price' => 10000000,
            'projection' => 0,
        ]);
    }

    public function test_manual_projection_save_creates_audit_log(): void
    {
        $this->actingAs($this->user);

        Livewire::test(RkapProjections::class)
            ->call('selectBudgetItem', $this->budgetItem->id)
            ->set('editingProjections.1', 1000000)
            ->set('editingProjections.2', 2000000)
            ->set('projectionNotes', 'Penyesuaian estimasi Q1')
            ->call('saveMonthlyProjections');

        $this->assertDatabaseHas('rkap_projection_logs', [
            'rkap_budget_item_id' => $this->budgetItem->id,
            'rkap_period_id'      => $this->activePeriod->id,
            'user_id'             => $this->user->id,
            'source'              => 'manual',
            'input_mode'          => 'monthly',
            'old_total'           => 0,
            'new_total'           => 3000000,
            'notes'               => 'Penyesuaian estimasi Q1',
        ]);

        $log = RkapProjectionLog::where('rkap_budget_item_id', $this->budgetItem->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals(1000000, $log->new_monthly[1]);
        $this->assertEquals(2000000, $log->new_monthly[2]);
    }

    public function test_view_history_loads_projection_logs(): void
    {
        $this->actingAs($this->user);

        RkapProjectionLog::create([
            'rkap_budget_item_id' => $this->budgetItem->id,
            'rkap_period_id'      => $this->activePeriod->id,
            'user_id'             => $this->user->id,
            'source'              => 'manual',
            'input_mode'          => 'monthly',
            'old_total'           => 0,
            'new_total'           => 5000000,
            'old_monthly'         => array_fill(1, 12, 0),
            'new_monthly'         => array_merge([1 => 5000000], array_fill(2, 11, 0)),
            'notes'               => 'Log Awal',
            'created_at'          => now(),
        ]);

        Livewire::test(\App\Livewire\Rkap\RkapProjectionHistory::class, ['budgetItemId' => $this->budgetItem->id])
            ->assertSet('budgetItemId', $this->budgetItem->id)
            ->assertSet('historyItemName', $this->budgetItem->account_code . ' — ' . $this->budgetItem->description)
            ->assertCount('historyLogs', 1);
    }

    public function test_projection_modal_displays_previous_change_notes(): void
    {
        $this->actingAs($this->user);

        RkapProjectionLog::create([
            'rkap_budget_item_id' => $this->budgetItem->id,
            'rkap_period_id'      => $this->activePeriod->id,
            'user_id'             => $this->user->id,
            'source'              => 'manual',
            'input_mode'          => 'monthly',
            'old_total'           => 0,
            'new_total'           => 5000000,
            'old_monthly'         => array_fill(1, 12, 0),
            'new_monthly'         => array_merge([1 => 5000000], array_fill(2, 11, 0)),
            'notes'               => 'Catatan Penyesuaian Anggaran Q2',
            'created_at'          => now(),
        ]);

        Livewire::test(RkapProjections::class)
            ->call('selectBudgetItem', $this->budgetItem->id)
            ->assertSee('Catatan Perubahan')
            ->assertSee('Catatan Penyesuaian Anggaran Q2')
            ->assertSee($this->user->name);
    }
}
