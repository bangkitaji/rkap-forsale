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
use App\Models\BudgetTransferApproval;
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
    protected User $kadeptSource;
    protected User $kadeptTarget;
    protected User $userDiffDir;
    protected Bureau $bureauSource;
    protected Bureau $bureauTarget;
    protected Bureau $bureauOtherDept;
    protected Department $deptSource;
    protected Department $deptTarget;
    protected Directorate $directorate;
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
        $roleKabiro = Role::firstOrCreate(['name' => 'kepala_biro', 'guard_name' => 'web']);
        $roleKadept = Role::firstOrCreate(['name' => 'kepala_departemen', 'guard_name' => 'web']);
        $viewPerm = Permission::firstOrCreate(['name' => 'rkap.transfer.view', 'guard_name' => 'web']);
        $createPerm = Permission::firstOrCreate(['name' => 'rkap.transfer.create', 'guard_name' => 'web']);
        $reviewPerm = Permission::firstOrCreate(['name' => 'rkap.transfer.review', 'guard_name' => 'web']);
        $roleKabiro->givePermissionTo([$viewPerm, $createPerm, $reviewPerm]);
        $roleKadept->givePermissionTo([$viewPerm, $reviewPerm]);

        // Setup organization
        $this->directorate = Directorate::create([
            'code' => 'DIR01',
            'name' => 'Directorate Test',
            'is_active' => true,
        ]);

        $otherDir = Directorate::create([
            'code' => 'DIR02',
            'name' => 'Directorate Other',
            'is_active' => true,
        ]);

        $this->deptSource = Department::create([
            'directorate_id' => $this->directorate->id,
            'code' => 'DEP01',
            'name' => 'Department Source',
            'is_active' => true,
        ]);

        $this->deptTarget = Department::create([
            'directorate_id' => $this->directorate->id,
            'code' => 'DEP02',
            'name' => 'Department Target (Same Dir)',
            'is_active' => true,
        ]);

        $deptDiffDir = Department::create([
            'directorate_id' => $otherDir->id,
            'code' => 'DEP03',
            'name' => 'Department Other Dir',
            'is_active' => true,
        ]);

        $this->bureauSource = Bureau::create([
            'department_id' => $this->deptSource->id,
            'code' => 'BUR01',
            'name' => 'Bureau Source',
            'is_active' => true,
        ]);

        $this->bureauTarget = Bureau::create([
            'department_id' => $this->deptSource->id,
            'code' => 'BUR02',
            'name' => 'Bureau Target (Same Dept)',
            'is_active' => true,
        ]);

        $this->bureauOtherDept = Bureau::create([
            'department_id' => $this->deptTarget->id,
            'code' => 'BUR03',
            'name' => 'Bureau Other Dept (Same Dir)',
            'is_active' => true,
        ]);

        $bureauDiffDir = Bureau::create([
            'department_id' => $deptDiffDir->id,
            'code' => 'BUR04',
            'name' => 'Bureau Other Dir',
            'is_active' => true,
        ]);

        // Users
        $this->userSource = User::create([
            'name' => 'Source User',
            'email' => 'source@example.com',
            'password' => bcrypt('password'),
            'bureau_id' => $this->bureauSource->id,
            'department_id' => $this->deptSource->id,
            'directorate_id' => $this->directorate->id,
        ]);
        $this->userSource->assignRole($roleKabiro);

        $this->userTarget = User::create([
            'name' => 'Target User',
            'email' => 'target@example.com',
            'password' => bcrypt('password'),
            'bureau_id' => $this->bureauTarget->id,
            'department_id' => $this->deptSource->id,
            'directorate_id' => $this->directorate->id,
        ]);
        $this->userTarget->assignRole($roleKabiro);

        $this->kadeptSource = User::create([
            'name' => 'Kadept Source User',
            'email' => 'kadept_source@example.com',
            'password' => bcrypt('password'),
            'department_id' => $this->deptSource->id,
            'directorate_id' => $this->directorate->id,
        ]);
        $this->kadeptSource->assignRole($roleKadept);

        $this->kadeptTarget = User::create([
            'name' => 'Kadept Target User',
            'email' => 'kadept_target@example.com',
            'password' => bcrypt('password'),
            'department_id' => $this->deptTarget->id,
            'directorate_id' => $this->directorate->id,
        ]);
        $this->kadeptTarget->assignRole($roleKadept);

        $this->userDiffDir = User::create([
            'name' => 'User Diff Dir',
            'email' => 'diffdir@example.com',
            'password' => bcrypt('password'),
            'bureau_id' => $bureauDiffDir->id,
            'department_id' => $deptDiffDir->id,
            'directorate_id' => $otherDir->id,
        ]);
        $this->userDiffDir->assignRole($roleKabiro);

        // Setup RKAP Period
        $this->period = RkapPeriod::create([
            'year' => (int) date('Y'),
            'title' => 'RKAP ' . date('Y'),
            'status' => 'open',
        ]);

        // Setup source submission (must be Approved to allow transfer)
        $this->submissionSource = RkapSubmission::create([
            'rkap_period_id' => $this->period->id,
            'bureau_id' => $this->bureauSource->id,
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

    public function test_can_create_intra_department_transfer_request(): void
    {
        $service = new BudgetTransferService();
        $transfer = $service->createTransfer(
            $this->userSource,
            $this->period->id,
            $this->userTarget->bureau_id,
            [$this->workPlan1->id],
            'Catatan Transfer Satu Departemen'
        );

        $this->assertDatabaseHas('budget_transfers', [
            'id' => $transfer->id,
            'transfer_type' => 'intra_department',
            'status' => BudgetTransferStatus::Pending->value,
            'total_amount' => 5000000,
            'notes' => 'Catatan Transfer Satu Departemen',
        ]);
        $this->assertFalse($transfer->isCrossDepartment());
    }

    public function test_can_create_cross_department_transfer_in_same_directorate(): void
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
            $this->bureauOtherDept->id,
            $itemsData,
            'Transfer Antar Departemen'
        );

        $this->assertDatabaseHas('budget_transfers', [
            'id' => $transfer->id,
            'transfer_type' => 'inter_department',
            'status' => BudgetTransferStatus::PendingSourceDept->value,
            'total_amount' => 2000000,
            'notes' => 'Transfer Antar Departemen',
        ]);
        $this->assertTrue($transfer->isCrossDepartment());
    }

    public function test_cross_department_transfer_to_different_directorate_fails(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Transfer budget hanya dapat dilakukan antar biro dalam satu direktorat yang sama.');

        $service = new BudgetTransferService();
        $itemsData = [
            [
                'work_plan_id' => $this->workPlan1->id,
                'budget_item_id' => $this->budgetItem1->id,
                'amount_transferred' => 1000000,
            ],
        ];

        $service->createTransfer(
            $this->userSource,
            $this->period->id,
            $this->userDiffDir->bureau_id,
            $itemsData,
            'Transfer Beda Dir'
        );
    }

    public function test_cross_department_four_step_approval_workflow(): void
    {
        $service = new BudgetTransferService();
        $targetUser = User::create([
            'name' => 'Target Other Dept User',
            'email' => 'otherdept@example.com',
            'password' => bcrypt('password'),
            'bureau_id' => $this->bureauOtherDept->id,
            'department_id' => $this->deptTarget->id,
            'directorate_id' => $this->directorate->id,
        ]);
        $targetUser->assignRole('kepala_biro');

        $itemsData = [
            [
                'work_plan_id' => $this->workPlan1->id,
                'budget_item_id' => $this->budgetItem1->id,
                'amount_transferred' => 2000000,
            ],
        ];

        // 1. Source Bureau submits transfer
        $transfer = $service->createTransfer(
            $this->userSource,
            $this->period->id,
            $this->bureauOtherDept->id,
            $itemsData,
            'Transfer Lintas Departemen'
        );

        $this->assertEquals(BudgetTransferStatus::PendingSourceDept->value, $transfer->status);

        // Verification of authorization at stage 1
        $this->assertTrue($transfer->canBeReviewedBy($this->kadeptSource));
        $this->assertFalse($transfer->canBeReviewedBy($this->kadeptTarget));
        $this->assertFalse($transfer->canBeReviewedBy($targetUser));

        // 2. Source Department Head approves -> Status becomes pending_target_dept
        $service->approveTransfer($transfer, $this->kadeptSource, 'Disetujui Kadep Pengusul');
        $transfer->refresh();

        $this->assertEquals(BudgetTransferStatus::PendingTargetDept->value, $transfer->status);
        $this->assertEquals($this->kadeptSource->id, $transfer->source_dept_approved_by);
        $this->assertNotNull($transfer->source_dept_approved_at);
        $this->assertEquals('Disetujui Kadep Pengusul', $transfer->source_dept_review_notes);

        // Verification of authorization at stage 2
        $this->assertFalse($transfer->canBeReviewedBy($this->kadeptSource));
        $this->assertTrue($transfer->canBeReviewedBy($this->kadeptTarget));
        $this->assertFalse($transfer->canBeReviewedBy($targetUser));

        // 3. Target Department Head approves -> Status becomes pending_target_bureau
        $service->approveTransfer($transfer, $this->kadeptTarget, 'Disetujui Kadep Penerima');
        $transfer->refresh();

        $this->assertEquals(BudgetTransferStatus::PendingTargetBureau->value, $transfer->status);
        $this->assertEquals($this->kadeptTarget->id, $transfer->target_dept_approved_by);
        $this->assertNotNull($transfer->target_dept_approved_at);
        $this->assertEquals('Disetujui Kadep Penerima', $transfer->target_dept_review_notes);

        // Verification of authorization at stage 3
        $this->assertFalse($transfer->canBeReviewedBy($this->kadeptSource));
        $this->assertFalse($transfer->canBeReviewedBy($this->kadeptTarget));
        $this->assertTrue($transfer->canBeReviewedBy($targetUser));

        // Source budget not deducted yet before final stage
        $this->assertEquals(5000000, (float) $this->budgetItem1->fresh()->total_price);

        // 4. Target Bureau Head accepts / approves -> Status becomes approved, funds transferred!
        $service->approveTransfer($transfer, $targetUser, 'Diterima oleh Kabiro Penerima');
        $transfer->refresh();

        $this->assertEquals(BudgetTransferStatus::Approved->value, $transfer->status);
        $this->assertEquals($targetUser->id, $transfer->reviewed_by);
        $this->assertEquals('Diterima oleh Kabiro Penerima', $transfer->review_notes);

        // Check budget deducted at source and created at target
        $this->assertEquals(3000000, (float) $this->budgetItem1->fresh()->total_price);

        $targetSubmission = RkapSubmission::where('rkap_period_id', $this->period->id)
            ->where('bureau_id', $this->bureauOtherDept->id)
            ->first();
        $this->assertNotNull($targetSubmission);
        $this->assertEquals(2000000, (float) $targetSubmission->total_budget);

        // Check approval audit trail table
        $this->assertDatabaseHas('budget_transfer_approvals', [
            'budget_transfer_id' => $transfer->id,
            'user_id' => $this->kadeptSource->id,
            'stage' => 'source_department',
            'action' => 'approved',
        ]);
        $this->assertDatabaseHas('budget_transfer_approvals', [
            'budget_transfer_id' => $transfer->id,
            'user_id' => $this->kadeptTarget->id,
            'stage' => 'target_department',
            'action' => 'approved',
        ]);
        $this->assertDatabaseHas('budget_transfer_approvals', [
            'budget_transfer_id' => $transfer->id,
            'user_id' => $targetUser->id,
            'stage' => 'target_bureau',
            'action' => 'approved',
        ]);
    }

    public function test_cross_department_rejection_at_source_dept(): void
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
            $this->bureauOtherDept->id,
            $itemsData,
            'Transfer Antar Departemen'
        );

        $service->rejectTransfer($transfer, $this->kadeptSource, 'Ditolak oleh Kadep Pengusul karena prioritas');
        $transfer->refresh();

        $this->assertEquals(BudgetTransferStatus::Rejected->value, $transfer->status);
        $this->assertEquals($this->kadeptSource->id, $transfer->reviewed_by);
        $this->assertEquals('Ditolak oleh Kadep Pengusul karena prioritas', $transfer->review_notes);

        $this->assertDatabaseHas('budget_transfer_approvals', [
            'budget_transfer_id' => $transfer->id,
            'user_id' => $this->kadeptSource->id,
            'stage' => 'source_department',
            'action' => 'rejected',
        ]);
    }

    public function test_cross_department_rejection_at_target_dept(): void
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
            $this->bureauOtherDept->id,
            $itemsData,
            'Transfer Antar Departemen'
        );

        // Stage 1 approve
        $service->approveTransfer($transfer, $this->kadeptSource, 'Ok');

        // Stage 2 reject
        $service->rejectTransfer($transfer, $this->kadeptTarget, 'Ditolak oleh Kadep Penerima');
        $transfer->refresh();

        $this->assertEquals(BudgetTransferStatus::Rejected->value, $transfer->status);
        $this->assertDatabaseHas('budget_transfer_approvals', [
            'budget_transfer_id' => $transfer->id,
            'user_id' => $this->kadeptTarget->id,
            'stage' => 'target_department',
            'action' => 'rejected',
        ]);
    }

    public function test_item_locked_during_cross_department_pending_stages(): void
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
            $this->bureauOtherDept->id,
            $itemsData,
            'Transfer 1'
        );

        $this->assertTrue($this->workPlan1->isLockedForTransfer());

        // Advance to next stage
        $service->approveTransfer($transfer, $this->kadeptSource, 'Approve stage 1');
        $this->assertTrue($this->workPlan1->isLockedForTransfer());

        // Attempting another transfer with same item must throw exception
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('sudah berada dalam proses transfer lain yang sedang pending');

        $service->createTransfer(
            $this->userSource,
            $this->period->id,
            $this->userTarget->bureau_id,
            $itemsData,
            'Transfer 2 overlapping'
        );
    }

    public function test_intra_dept_partial_budget_transfer_approval_deducts_source_and_creates_target_budget(): void
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

    public function test_admin_can_delete_zero_budget_transferred_item(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $adminUser->assignRole($adminRole);

        $service = new BudgetTransferService();
        $itemsData = [
            [
                'work_plan_id' => $this->workPlan1->id,
                'budget_item_id' => $this->budgetItem1->id,
                'amount_transferred' => 5000000, // 100% transfer, leaving 0 budget
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

        $transferItem = $transfer->items()->first();

        // Source budget item has 0 price
        $this->assertEquals(0, (float) $this->budgetItem1->fresh()->total_price);

        // Admin cleans up zero-budget item
        $service->deleteZeroBudgetTransferredItem($adminUser, $transferItem->id);

        $this->assertDatabaseMissing('rkap_budget_items', ['id' => $this->budgetItem1->id]);
    }

    public function test_non_admin_cannot_delete_zero_budget_transferred_item(): void
    {
        $service = new BudgetTransferService();
        $itemsData = [
            [
                'work_plan_id' => $this->workPlan1->id,
                'budget_item_id' => $this->budgetItem1->id,
                'amount_transferred' => 5000000,
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

        $transferItem = $transfer->items()->first();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Hanya Administrator');

        $service->deleteZeroBudgetTransferredItem($this->userSource, $transferItem->id);
    }

    public function test_admin_cannot_delete_item_with_remaining_budget(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin2@example.com',
            'password' => bcrypt('password'),
        ]);
        $adminUser->assignRole($adminRole);

        $service = new BudgetTransferService();
        $itemsData = [
            [
                'work_plan_id' => $this->workPlan1->id,
                'budget_item_id' => $this->budgetItem1->id,
                'amount_transferred' => 2000000, // Partial transfer, leaving 3,000,000 budget
            ],
        ];

        $transfer = $service->createTransfer(
            $this->userSource,
            $this->period->id,
            $this->userTarget->bureau_id,
            $itemsData,
            'Transfer Partial'
        );

        $service->approveTransfer($transfer, $this->userTarget, 'Approved Partial');

        $transferItem = $transfer->items()->first();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Hanya kegiatan dengan sisa budget Rp 0');

        $service->deleteZeroBudgetTransferredItem($adminUser, $transferItem->id);
    }
}
