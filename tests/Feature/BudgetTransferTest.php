<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\RkapPeriod;
use App\Models\RkapSubmission;
use App\Models\RkapWorkPlan;
use App\Models\RkapBudgetItem;
use App\Models\WorkPlan;
use App\Models\Activity;
use App\Models\Coa;
use App\Models\Bureau;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\User;
use App\Models\BudgetTransfer;
use App\Models\BudgetTransferItem;
use App\Enums\BudgetTransferStatus;
use App\Enums\SubmissionStatus;
use App\Services\BudgetTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class BudgetTransferTest extends TestCase
{
    use RefreshDatabase;

    protected User $userSource;
    protected User $userTarget;
    protected RkapPeriod $period;
    protected RkapSubmission $submissionSource;
    protected RkapWorkPlan $workPlan1;

    protected function setUp(): void
    {
        parent::setUp();

        // Run migrations
        Artisan::call('migrate');

        // Setup roles & permissions
        $role = Role::firstOrCreate(['name' => 'kepala_biro', 'guard_name' => 'web']);
        $viewPerm = Permission::firstOrCreate(['name' => 'rkap.transfer.view', 'guard_name' => 'web']);
        $createPerm = Permission::firstOrCreate(['name' => 'rkap.transfer.create', 'guard_name' => 'web']);
        $reviewPerm = Permission::firstOrCreate(['name' => 'rkap.transfer.review', 'guard_name' => 'web']);
        $role->givePermissionTo([$viewPerm, $createPerm, $reviewPerm]);

        // Setup organization
        $dir = Directorate::create([
            'code' => 'DIR01',
            'name' => 'Directorate Test',
            'is_active' => true,
        ]);

        $dept = Department::create([
            'directorate_id' => $dir->id,
            'code' => 'DEP01',
            'name' => 'Department Test',
            'is_active' => true,
        ]);

        $bureauSource = Bureau::create([
            'department_id' => $dept->id,
            'code' => 'BUR01',
            'name' => 'Bureau Source',
            'is_active' => true,
        ]);

        $bureauTarget = Bureau::create([
            'department_id' => $dept->id,
            'code' => 'BUR02',
            'name' => 'Bureau Target',
            'is_active' => true,
        ]);

        $this->userSource = User::create([
            'name' => 'Source User',
            'email' => 'source@example.com',
            'password' => bcrypt('password'),
            'bureau_id' => $bureauSource->id,
        ]);
        $this->userSource->assignRole($role);

        $this->userTarget = User::create([
            'name' => 'Target User',
            'email' => 'target@example.com',
            'password' => bcrypt('password'),
            'bureau_id' => $bureauTarget->id,
        ]);
        $this->userTarget->assignRole($role);

        // Setup RKAP Period
        $this->period = RkapPeriod::create([
            'year' => (int) date('Y'),
            'title' => 'RKAP ' . date('Y'),
            'status' => 'open',
        ]);

        // Setup source submission (must be Approved to allow transfer)
        $this->submissionSource = RkapSubmission::create([
            'rkap_period_id' => $this->period->id,
            'bureau_id' => $bureauSource->id,
            'created_by' => $this->userSource->id,
            'status' => SubmissionStatus::Approved->value,
            'total_budget' => 5000000,
        ]);

        $this->submissionSource->versions()->create([
            'version_number' => 1,
            'created_by' => $this->userSource->id,
            'change_type' => 'initial',
            'change_reason' => 'Initial submission',
            'total_budget' => 5000000,
            'snapshot_data' => [],
        ]);

        // Setup Work Plan and Budget Item
        $masterWp = WorkPlan::create(['code' => 'WP001', 'title' => 'Master Work Plan']);
        $masterAct = Activity::create(['work_plan_id' => $masterWp->id, 'code' => 'ACT001', 'title' => 'Master Activity']);
        $coa = Coa::create(['code' => '510101', 'title' => 'Belanja Gaji']);

        $this->workPlan1 = RkapWorkPlan::create([
            'rkap_submission_id' => $this->submissionSource->id,
            'work_plan_id' => $masterWp->id,
            'activity_id' => $masterAct->id,
            'program_name' => 'Master Activity',
            'quantity' => 1,
        ]);

        $budgetItem = RkapBudgetItem::create([
            'rkap_work_plan_id' => $this->workPlan1->id,
            'account_code' => $coa->code,
            'description' => $coa->title,
            'quantity' => 10,
            'unit_price' => 500000,
            'total_price' => 5000000,
        ]);

        $budgetItem->monthlies()->create(['month' => 1, 'amount' => 5000000]);
        $budgetItem->cashOuts()->create(['month' => 1, 'amount' => 5000000]);
        $budgetItem->realizations()->create(['month' => 1, 'amount' => 100000, 'rkap_period_id' => $this->period->id]);
        $budgetItem->projections()->create(['month' => 1, 'amount' => 100000, 'rkap_period_id' => $this->period->id]);
    }

    public function test_can_create_transfer_request(): void
    {
        $service = new BudgetTransferService();
        $transfer = $service->createTransfer(
            $this->userSource,
            $this->period->id,
            $this->userTarget->bureau_id,
            [$this->workPlan1->id],
            'Catatan Transfer'
        );

        $this->assertDatabaseHas('budget_transfers', [
            'id' => $transfer->id,
            'status' => BudgetTransferStatus::Pending->value,
            'total_amount' => 5000000,
            'notes' => 'Catatan Transfer',
        ]);

        $this->assertDatabaseHas('budget_transfer_items', [
            'budget_transfer_id' => $transfer->id,
            'rkap_work_plan_id' => $this->workPlan1->id,
        ]);

        $this->assertTrue($this->workPlan1->isLockedForTransfer());
    }

    public function test_target_bureau_can_approve_transfer(): void
    {
        $service = new BudgetTransferService();
        $transfer = $service->createTransfer(
            $this->userSource,
            $this->period->id,
            $this->userTarget->bureau_id,
            [$this->workPlan1->id],
            'Catatan Transfer'
        );

        $service->approveTransfer($transfer, $this->userTarget, 'Approved by target');

        // Check transfer status
        $this->assertEquals(BudgetTransferStatus::Approved->value, $transfer->fresh()->status);

        // Check target submission created and work plan moved
        $targetSubmission = RkapSubmission::where('rkap_period_id', $this->period->id)
            ->where('bureau_id', $this->userTarget->bureau_id)
            ->first();

        $this->assertNotNull($targetSubmission);
        $this->assertEquals(SubmissionStatus::Approved->value, $targetSubmission->status);
        $this->assertEquals($targetSubmission->id, $this->workPlan1->fresh()->rkap_submission_id);

        // Verify total budget recalculated
        $this->assertEquals(0, $this->submissionSource->fresh()->total_budget);
        $this->assertEquals(5000000, $targetSubmission->fresh()->total_budget);

        // Verify version snapshots created
        $this->assertEquals(2, $this->submissionSource->versions()->count());
        $this->assertEquals(1, $targetSubmission->versions()->count());
    }

    public function test_target_bureau_can_reject_transfer(): void
    {
        $service = new BudgetTransferService();
        $transfer = $service->createTransfer(
            $this->userSource,
            $this->period->id,
            $this->userTarget->bureau_id,
            [$this->workPlan1->id],
            'Catatan Transfer'
        );

        $service->rejectTransfer($transfer, $this->userTarget, 'Rejected by target');

        $this->assertEquals(BudgetTransferStatus::Rejected->value, $transfer->fresh()->status);
        $this->assertEquals($this->submissionSource->id, $this->workPlan1->fresh()->rkap_submission_id);
    }

    public function test_sender_can_cancel_transfer(): void
    {
        $service = new BudgetTransferService();
        $transfer = $service->createTransfer(
            $this->userSource,
            $this->period->id,
            $this->userTarget->bureau_id,
            [$this->workPlan1->id],
            'Catatan Transfer'
        );

        $service->cancelTransfer($transfer, $this->userSource);

        $this->assertEquals(BudgetTransferStatus::Cancelled->value, $transfer->fresh()->status);
    }
}
