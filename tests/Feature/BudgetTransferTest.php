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
    protected RkapBudgetItem $budgetItem1;

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
            'program_code' => 'ACT001',
            'program_name' => 'Master Activity',
            'quantity' => 1,
        ]);

        $this->budgetItem1 = RkapBudgetItem::create([
            'rkap_work_plan_id' => $this->workPlan1->id,
            'account_code' => $coa->code,
            'description' => $coa->title,
            'quantity' => 10,
            'unit_price' => 500000,
            'total_price' => 5000000,
        ]);

        $this->budgetItem1->monthlies()->create(['month' => 1, 'amount' => 5000000]);
        $this->budgetItem1->cashOuts()->create(['month' => 1, 'amount' => 5000000]);
    }

    public function test_can_create_transfer_request_legacy(): void
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
    }

    public function test_can_create_partial_budget_transfer_request(): void
    {
        $service = new BudgetTransferService();
        $itemsData = [
            [
                'work_plan_id' => $this->workPlan1->id,
                'budget_item_id' => $this->budgetItem1->id,
                'amount_transferred' => 2000000,
            ],
        ];

        $transfer = $service->createTransfer(
            $this->userSource,
            $this->period->id,
            $this->userTarget->bureau_id,
            $itemsData,
            'Transfer Partial 2 Juta'
        );

        $this->assertDatabaseHas('budget_transfers', [
            'id' => $transfer->id,
            'status' => BudgetTransferStatus::Pending->value,
            'total_amount' => 2000000,
            'notes' => 'Transfer Partial 2 Juta',
        ]);

        $this->assertDatabaseHas('budget_transfer_items', [
            'budget_transfer_id' => $transfer->id,
            'rkap_work_plan_id' => $this->workPlan1->id,
            'rkap_budget_item_id' => $this->budgetItem1->id,
            'amount_transferred' => 2000000,
        ]);
    }

    public function test_partial_budget_transfer_approval_deducts_source_and_creates_target_budget(): void
    {
        $service = new BudgetTransferService();
        $itemsData = [
            [
                'work_plan_id' => $this->workPlan1->id,
                'budget_item_id' => $this->budgetItem1->id,
                'amount_transferred' => 2000000,
            ],
        ];

        $transfer = $service->createTransfer(
            $this->userSource,
            $this->period->id,
            $this->userTarget->bureau_id,
            $itemsData,
            'Transfer Partial 2 Juta'
        );

        $service->approveTransfer($transfer, $this->userTarget, 'Approved Partial');

        // Verify status approved
        $this->assertEquals(BudgetTransferStatus::Approved->value, $transfer->fresh()->status);

        // Verify source budget item reduced to 3,000,000
        $this->assertEquals(3000000, (float) $this->budgetItem1->fresh()->total_price);
        $this->assertEquals(3000000, (float) $this->budgetItem1->monthlies()->where('month', 1)->first()->amount);

        // Verify target submission created with 2,000,000
        $targetSubmission = RkapSubmission::where('rkap_period_id', $this->period->id)
            ->where('bureau_id', $this->userTarget->bureau_id)
            ->first();

        $this->assertNotNull($targetSubmission);
        $this->assertEquals(2000000, (float) $targetSubmission->total_budget);

        // Verify target work plan and budget item created
        $targetWorkPlan = RkapWorkPlan::where('rkap_submission_id', $targetSubmission->id)->first();
        $this->assertNotNull($targetWorkPlan);
        $this->assertEquals('Master Activity', $targetWorkPlan->program_name);

        $targetBudgetItem = RkapBudgetItem::where('rkap_work_plan_id', $targetWorkPlan->id)->first();
        $this->assertNotNull($targetBudgetItem);
        $this->assertEquals(2000000, (float) $targetBudgetItem->total_price);
        $this->assertEquals(2000000, (float) $targetBudgetItem->monthlies()->where('month', 1)->first()->amount);

        // Verify source submission total budget recalculated
        $this->assertEquals(3000000, (float) $this->submissionSource->fresh()->total_budget);
    }

    public function test_full_budget_transfer_transfers_realization_projection_and_cashout(): void
    {
        $this->budgetItem1->realizations()->create(['month' => 1, 'amount' => 100000, 'rkap_period_id' => $this->period->id]);
        $this->budgetItem1->projections()->create(['month' => 1, 'amount' => 100000, 'rkap_period_id' => $this->period->id]);

        $service = new BudgetTransferService();
        $itemsData = [
            [
                'work_plan_id' => $this->workPlan1->id,
                'budget_item_id' => $this->budgetItem1->id,
                'amount_transferred' => 5000000, // 100% transfer
            ],
        ];

        $transfer = $service->createTransfer(
            $this->userSource,
            $this->period->id,
            $this->userTarget->bureau_id,
            $itemsData,
            'Transfer 100%'
        );

        $service->approveTransfer($transfer, $this->userTarget, 'Approved 100%');

        $targetSubmission = RkapSubmission::where('rkap_period_id', $this->period->id)
            ->where('bureau_id', $this->userTarget->bureau_id)
            ->first();

        $targetWorkPlan = RkapWorkPlan::where('rkap_submission_id', $targetSubmission->id)->first();
        $targetBudgetItem = RkapBudgetItem::where('rkap_work_plan_id', $targetWorkPlan->id)->first();

        // Verify realization, projection, and cashout moved to target item
        $this->assertEquals(1, $targetBudgetItem->realizations()->count());
        $this->assertEquals(100000, (float) $targetBudgetItem->realizations()->first()->amount);
        $this->assertEquals(0, $this->budgetItem1->fresh()->realizations()->count());
    }

    public function test_cannot_transfer_more_than_available_budget(): void
    {
        $this->expectException(\Exception::class);

        $service = new BudgetTransferService();
        $itemsData = [
            [
                'work_plan_id' => $this->workPlan1->id,
                'budget_item_id' => $this->budgetItem1->id,
                'amount_transferred' => 6000000, // Exceeds 5,000,000
            ],
        ];

        $service->createTransfer(
            $this->userSource,
            $this->period->id,
            $this->userTarget->bureau_id,
            $itemsData,
            'Transfer Over Budget'
        );
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
