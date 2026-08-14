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
use App\Enums\BudgetTransferStatus;
use App\Enums\SubmissionStatus;
use Livewire\Livewire;
use App\Livewire\Rkap\BudgetTransferCreate;
use App\Livewire\Rkap\BudgetTransferList;
use App\Livewire\Rkap\BudgetTransferReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class BudgetTransferLivewireTest extends TestCase
{
    use RefreshDatabase;

    protected User $userSource;
    protected User $kadeptSource;
    protected User $kadeptTarget;
    protected User $userTarget;
    protected Bureau $bureauSource;
    protected Bureau $bureauTarget;
    protected RkapPeriod $period;
    protected RkapSubmission $submissionSource;
    protected RkapWorkPlan $workPlan1;
    protected RkapBudgetItem $budgetItem1;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate');

        $roleKabiro = Role::firstOrCreate(['name' => 'kepala_biro', 'guard_name' => 'web']);
        $roleKadept = Role::firstOrCreate(['name' => 'kepala_departemen', 'guard_name' => 'web']);
        $viewPerm = Permission::firstOrCreate(['name' => 'rkap.transfer.view', 'guard_name' => 'web']);
        $createPerm = Permission::firstOrCreate(['name' => 'rkap.transfer.create', 'guard_name' => 'web']);
        $reviewPerm = Permission::firstOrCreate(['name' => 'rkap.transfer.review', 'guard_name' => 'web']);
        $roleKabiro->givePermissionTo([$viewPerm, $createPerm, $reviewPerm]);
        $roleKadept->givePermissionTo([$viewPerm, $reviewPerm]);

        $dir = Directorate::create(['code' => 'DIR01', 'name' => 'Directorate Test', 'is_active' => true]);
        $dept1 = Department::create(['directorate_id' => $dir->id, 'code' => 'DEP01', 'name' => 'Dept 1', 'is_active' => true]);
        $dept2 = Department::create(['directorate_id' => $dir->id, 'code' => 'DEP02', 'name' => 'Dept 2', 'is_active' => true]);

        $this->bureauSource = Bureau::create(['department_id' => $dept1->id, 'code' => 'BUR01', 'name' => 'Bureau 1', 'is_active' => true]);
        $this->bureauTarget = Bureau::create(['department_id' => $dept2->id, 'code' => 'BUR02', 'name' => 'Bureau 2', 'is_active' => true]);

        $this->userSource = User::create([
            'name' => 'Source User', 'email' => 'source@test.com', 'password' => bcrypt('password'),
            'bureau_id' => $this->bureauSource->id, 'department_id' => $dept1->id, 'directorate_id' => $dir->id,
        ]);
        $this->userSource->assignRole($roleKabiro);

        $this->kadeptSource = User::create([
            'name' => 'Kadep 1', 'email' => 'kadept1@test.com', 'password' => bcrypt('password'),
            'department_id' => $dept1->id, 'directorate_id' => $dir->id,
        ]);
        $this->kadeptSource->assignRole($roleKadept);

        $this->kadeptTarget = User::create([
            'name' => 'Kadep 2', 'email' => 'kadept2@test.com', 'password' => bcrypt('password'),
            'department_id' => $dept2->id, 'directorate_id' => $dir->id,
        ]);
        $this->kadeptTarget->assignRole($roleKadept);

        $this->userTarget = User::create([
            'name' => 'Target User', 'email' => 'target@test.com', 'password' => bcrypt('password'),
            'bureau_id' => $this->bureauTarget->id, 'department_id' => $dept2->id, 'directorate_id' => $dir->id,
        ]);
        $this->userTarget->assignRole($roleKabiro);

        $this->period = RkapPeriod::create(['year' => (int) date('Y'), 'title' => 'RKAP ' . date('Y'), 'status' => 'open']);

        $this->submissionSource = RkapSubmission::create([
            'rkap_period_id' => $this->period->id,
            'bureau_id' => $this->bureauSource->id,
            'created_by' => $this->userSource->id,
            'status' => SubmissionStatus::Approved->value,
            'total_budget' => 5000000,
        ]);

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
            'quantity' => 1,
            'unit_price' => 5000000,
            'total_price' => 5000000,
        ]);
        $this->budgetItem1->monthlies()->create(['month' => 1, 'amount' => 5000000]);
    }

    public function test_livewire_budget_transfer_create_render_and_submit(): void
    {
        Livewire::actingAs($this->userSource)
            ->test(BudgetTransferCreate::class)
            ->assertSee('Konfigurasi Transfer')
            ->set('periodId', $this->period->id)
            ->set('targetBureauId', $this->bureauTarget->id)
            ->set('selectedItems.' . $this->budgetItem1->id, true)
            ->set('transferAmounts.' . $this->budgetItem1->id, 2500000)
            ->set('notes', 'Transfer via Livewire')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('rkap-budget-transfers'));

        $this->assertDatabaseHas('budget_transfers', [
            'source_bureau_id' => $this->bureauSource->id,
            'target_bureau_id' => $this->bureauTarget->id,
            'transfer_type' => 'inter_department',
            'status' => BudgetTransferStatus::PendingSourceDept->value,
            'total_amount' => 2500000,
        ]);
    }

    public function test_livewire_budget_transfer_list_and_review_flow(): void
    {
        $transfer = BudgetTransfer::create([
            'rkap_period_id' => $this->period->id,
            'source_bureau_id' => $this->bureauSource->id,
            'target_bureau_id' => $this->bureauTarget->id,
            'source_submission_id' => $this->submissionSource->id,
            'requested_by' => $this->userSource->id,
            'status' => BudgetTransferStatus::PendingSourceDept->value,
            'transfer_type' => 'inter_department',
            'total_amount' => 2500000,
        ]);

        $transfer->items()->create([
            'rkap_work_plan_id' => $this->workPlan1->id,
            'rkap_budget_item_id' => $this->budgetItem1->id,
            'amount_transferred' => 2500000,
            'monthly_distribution' => [1 => 2500000],
            'snapshot_data' => [],
        ]);

        // List render for Kadep Source (outgoing tab)
        Livewire::actingAs($this->kadeptSource)
            ->test(BudgetTransferList::class)
            ->set('activeTab', 'outgoing')
            ->assertSee('Antar Departemen')
            ->assertSee('Bureau 2');

        // List render for Kadep Target (incoming tab)
        Livewire::actingAs($this->kadeptTarget)
            ->test(BudgetTransferList::class)
            ->assertSee('Antar Departemen')
            ->assertSee('Bureau 1');

        // Review by Kadep Source -> advances to PendingTargetDept
        Livewire::actingAs($this->kadeptSource)
            ->test(BudgetTransferReview::class, ['id' => $transfer->id])
            ->assertSee('Approval Kadep Pengusul')
            ->set('reviewNotes', 'Approved by Kadep 1')
            ->call('approve')
            ->assertRedirect(route('rkap-budget-transfers'));

        $this->assertEquals(BudgetTransferStatus::PendingTargetDept->value, $transfer->fresh()->status);

        // Review by Kadep Target -> advances to PendingTargetBureau
        Livewire::actingAs($this->kadeptTarget)
            ->test(BudgetTransferReview::class, ['id' => $transfer->id])
            ->assertSee('Approval Kadep Penerima')
            ->set('reviewNotes', 'Approved by Kadep 2')
            ->call('approve')
            ->assertRedirect(route('rkap-budget-transfers'));

        $this->assertEquals(BudgetTransferStatus::PendingTargetBureau->value, $transfer->fresh()->status);

        // Review by Target Kabiro -> advances to Approved
        Livewire::actingAs($this->userTarget)
            ->test(BudgetTransferReview::class, ['id' => $transfer->id])
            ->assertSee('Penerimaan & Approval Kabiro Penerima')
            ->set('reviewNotes', 'Accepted by Target Kabiro')
            ->call('approve')
            ->assertRedirect(route('rkap-budget-transfers'));

        $this->assertEquals(BudgetTransferStatus::Approved->value, $transfer->fresh()->status);
    }
}
