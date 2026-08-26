<?php

namespace Tests\Feature;

use Tests\TestCase;
use Livewire\Livewire;
use App\Livewire\Rkap\RkapRealizationUpload;
use App\Models\RkapPeriod;
use App\Models\RkapBudgetItem;
use App\Models\RkapBudgetItemProjection;
use App\Models\RkapBudgetItemRealization;
use App\Models\RkapProjectionLog;
use App\Models\RkapSubmission;
use App\Models\Bureau;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\User;
use App\Models\WorkPlan;
use App\Models\RkapWorkPlan;
use App\Services\ProjectionRecalculationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class ProjectionRecalculationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected RkapPeriod $period;
    protected RkapBudgetItem $budgetItemWithProjections;
    protected RkapBudgetItem $budgetItemWithoutProjections;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['name' => 'admin']);
        $permRealizationUpload = Permission::firstOrCreate(['name' => 'rkap.realization.upload', 'guard_name' => 'web']);
        $roleAdmin->givePermissionTo($permRealizationUpload);

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->admin->assignRole($roleAdmin);

        $this->period = RkapPeriod::create([
            'year' => (int) date('Y'),
            'title' => 'RKAP ' . date('Y'),
            'status' => 'finalized',
            'submission_start' => now()->subDay(),
            'submission_end' => now()->addDay(),
        ]);

        $directorate = Directorate::create(['code' => 'D1', 'name' => 'Dir 1']);
        $department = Department::create(['directorate_id' => $directorate->id, 'code' => 'DP1', 'name' => 'Dept 1']);
        $bureau = Bureau::create(['department_id' => $department->id, 'code' => 'B1', 'name' => 'Bur 1']);

        $submission = RkapSubmission::create([
            'rkap_period_id' => $this->period->id,
            'bureau_id' => $bureau->id,
            'created_by' => $this->admin->id,
            'status' => 'approved',
            'total_budget' => 120000,
        ]);

        $wpMaster = WorkPlan::create([
            'code' => 'WP001',
            'title' => 'Work Plan 1',
        ]);

        $workPlan = RkapWorkPlan::create([
            'rkap_submission_id' => $submission->id,
            'work_plan_id' => $wpMaster->id,
            'program_code' => 'WP001',
            'program_name' => 'Work Plan 1',
        ]);

        // Budget item 1: Has monthly projections (1000 each for 12 months = 12000)
        $this->budgetItemWithProjections = RkapBudgetItem::create([
            'rkap_work_plan_id' => $workPlan->id,
            'account_code' => '521111',
            'description' => 'Item With Projections',
            'quantity' => 1,
            'unit_price' => 50000,
            'total_price' => 50000,
            'projection' => 12000,
        ]);

        for ($m = 1; $m <= 12; $m++) {
            RkapBudgetItemProjection::create([
                'rkap_budget_item_id' => $this->budgetItemWithProjections->id,
                'rkap_period_id' => $this->period->id,
                'month' => $m,
                'amount' => 1000,
                'inputted_by' => $this->admin->id,
            ]);
        }

        // Budget item 2: No projections (0 projection, no monthly records)
        $this->budgetItemWithoutProjections = RkapBudgetItem::create([
            'rkap_work_plan_id' => $workPlan->id,
            'account_code' => '521112',
            'description' => 'Item Without Projections',
            'quantity' => 1,
            'unit_price' => 50000,
            'total_price' => 50000,
            'projection' => 0,
        ]);
    }

    public function test_projection_recalculates_on_single_month_realization_upload(): void
    {
        $this->actingAs($this->admin);

        // Upload realization for month 1 with amount 2500 (previously projection was 1000)
        // Expected new total projection: 2500 + 11 * 1000 = 13500
        $csvContent = "budget_item_id,month,amount\n";
        $csvContent .= "{$this->budgetItemWithProjections->id},1,2500\n";
        $csvContent .= "{$this->budgetItemWithoutProjections->id},1,3000\n";

        $file = UploadedFile::fake()->createWithContent('realisasi_jan.csv', $csvContent);

        Livewire::test(RkapRealizationUpload::class)
            ->set('periodId', $this->period->id)
            ->set('month', 1)
            ->set('file', $file)
            ->call('uploadAndImport')
            ->assertHasNoErrors();

        // Check budgetItemWithProjections has updated projection
        $this->budgetItemWithProjections->refresh();
        $this->assertEquals(13500.0, (float) $this->budgetItemWithProjections->projection);

        // Check month 1 projection in DB is updated to 2500
        $month1Proj = RkapBudgetItemProjection::where('rkap_budget_item_id', $this->budgetItemWithProjections->id)
            ->where('month', 1)
            ->first();
        $this->assertNotNull($month1Proj);
        $this->assertEquals(2500.0, (float) $month1Proj->amount);

        // Check budgetItemWithoutProjections is skipped (Option B: projection stays 0, no monthly projections created)
        $this->budgetItemWithoutProjections->refresh();
        $this->assertEquals(0.0, (float) $this->budgetItemWithoutProjections->projection);
        $this->assertEquals(0, RkapBudgetItemProjection::where('rkap_budget_item_id', $this->budgetItemWithoutProjections->id)->count());

        // Check audit log was created for budgetItemWithProjections
        $log = RkapProjectionLog::where('rkap_budget_item_id', $this->budgetItemWithProjections->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals(12000.0, (float) $log->old_total);
        $this->assertEquals(13500.0, (float) $log->new_total);
        $this->assertEquals('realization_upload', $log->source);
    }

    public function test_projection_recalculates_on_realization_delete(): void
    {
        $this->actingAs($this->admin);

        // Upload realization month 1 = 2500
        $realization = RkapBudgetItemRealization::create([
            'rkap_budget_item_id' => $this->budgetItemWithProjections->id,
            'rkap_period_id' => $this->period->id,
            'month' => 1,
            'amount' => 2500,
            'uploaded_by' => $this->admin->id,
            'uploaded_at' => now(),
        ]);

        // Sync projection first
        ProjectionRecalculationService::recalculateForBudgetItems(
            [$this->budgetItemWithProjections->id],
            $this->period->id,
            $this->admin->id
        );

        $this->budgetItemWithProjections->refresh();
        $this->assertEquals(13500.0, (float) $this->budgetItemWithProjections->projection);

        // Delete realization via Livewire
        Livewire::test(RkapRealizationUpload::class)
            ->set('periodId', $this->period->id)
            ->call('deleteRealization', $realization->id)
            ->assertHasNoErrors();

        // Verify realization deleted
        $this->assertDatabaseMissing('rkap_budget_item_realizations', ['id' => $realization->id]);

        // Verify projection recalculates
        $this->budgetItemWithProjections->refresh();
        // Since month 1 is open, it keeps its current stored projection (2500) or recalculates
        $this->assertDatabaseHas('rkap_projection_logs', [
            'rkap_budget_item_id' => $this->budgetItemWithProjections->id,
            'source' => 'realization_delete',
        ]);
    }

    public function test_projection_recalculates_on_mass_update(): void
    {
        $this->actingAs($this->admin);

        \App\Models\Setting::set('rkap_closing_day', 1);

        $component = Livewire::test(RkapRealizationUpload::class)->set('periodId', $this->period->id);
        $lastClosed = $component->get('lastClosedMonth');

        if (!$lastClosed) {
            $this->markTestSkipped('No closed months available for this test run.');
        }

        $csvRows = ["budget_item_id,month,amount"];
        for ($m = 1; $m <= $lastClosed; $m++) {
            $csvRows[] = "{$this->budgetItemWithProjections->id},{$m},1500";
        }
        $csvContent = implode("\n", $csvRows) . "\n";

        $file = UploadedFile::fake()->createWithContent('mass_update.csv', $csvContent);

        Livewire::test(RkapRealizationUpload::class)
            ->set('periodId', $this->period->id)
            ->set('massUpdateFile', $file)
            ->call('massUpdateUpload')
            ->assertHasNoErrors();

        // Expected total: $lastClosed * 1500 + (12 - $lastClosed) * 1000
        $expectedTotal = ($lastClosed * 1500) + ((12 - $lastClosed) * 1000);
        $this->budgetItemWithProjections->refresh();
        $this->assertEquals((float) $expectedTotal, (float) $this->budgetItemWithProjections->projection);

        $this->assertDatabaseHas('rkap_projection_logs', [
            'rkap_budget_item_id' => $this->budgetItemWithProjections->id,
            'source' => 'realization_mass_update',
            'new_total' => $expectedTotal,
        ]);
    }
}
