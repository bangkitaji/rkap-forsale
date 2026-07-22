<?php

namespace Tests\Feature;

use Tests\TestCase;
use Livewire\Livewire;
use App\Livewire\Rkap\RkapApprovalReview;
use App\Models\RkapPeriod;
use App\Models\Bureau;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\User;
use App\Models\RkapSubmission;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RkapReviewTest extends TestCase
{
    use RefreshDatabase;

    protected User $creator;
    protected User $kadept;
    protected User $direksi;
    protected User $verifikator;
    protected User $president;
    protected RkapPeriod $period;
    protected RkapSubmission $submission;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create Roles
        $roleKepalaBiro = Role::create(['name' => 'kepala_biro']);
        $roleKepalaDept = Role::create(['name' => 'kepala_departemen']);
        $roleDireksi = Role::create(['name' => 'direksi']);
        $roleVerifikator = Role::create(['name' => 'verifikator']);

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

        $bureau = Bureau::create([
            'department_id' => $department->id,
            'code' => 'BUR01',
            'name' => 'Bureau Test',
            'is_active' => true,
        ]);

        // 3. Create Users
        $this->creator = User::create([
            'name' => 'Creator User',
            'email' => 'creator@example.com',
            'password' => bcrypt('password'),
            'bureau_id' => $bureau->id,
            'department_id' => $department->id,
            'directorate_id' => $directorate->id,
        ]);
        $this->creator->assignRole($roleKepalaBiro);

        $this->kadept = User::create([
            'name' => 'Kadept User',
            'email' => 'kadept@example.com',
            'password' => bcrypt('password'),
            'department_id' => $department->id,
            'directorate_id' => $directorate->id,
        ]);
        $this->kadept->assignRole($roleKepalaDept);

        $this->direksi = User::create([
            'name' => 'Direksi User',
            'email' => 'direksi@example.com',
            'password' => bcrypt('password'),
            'directorate_id' => $directorate->id,
        ]);
        $this->direksi->assignRole($roleDireksi);

        $this->verifikator = User::create([
            'name' => 'Verifikator User',
            'email' => 'verifikator@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->verifikator->assignRole($roleVerifikator);

        $rolePresident = Role::create(['name' => 'direktur_utama']);
        $this->president = User::create([
            'name' => 'President User',
            'email' => 'president@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->president->assignRole($rolePresident);

        // 4. Period & Submission
        $this->period = RkapPeriod::create([
            'year' => 2026,
            'title' => 'RKAP 2026',
            'status' => 'open',
            'submission_start' => now()->subDay(),
            'submission_end' => now()->addDay(),
        ]);

        $this->submission = RkapSubmission::create([
            'rkap_period_id' => $this->period->id,
            'bureau_id' => $bureau->id,
            'created_by' => $this->creator->id,
            'status' => 'submitted', // Initially submitted, waiting for Kadep
            'total_budget' => 50000,
        ]);
    }

    public function test_kadept_can_see_approval_buttons_in_submitted_status(): void
    {
        $this->actingAs($this->kadept);

        Livewire::test(RkapApprovalReview::class, ['id' => $this->submission->id])
            ->assertStatus(200)
            ->assertSee('Setujui RKAP')
            ->assertSee('Minta Revisi');
    }

    public function test_direksi_cannot_see_approval_buttons_in_submitted_status(): void
    {
        $this->actingAs($this->direksi);

        Livewire::test(RkapApprovalReview::class, ['id' => $this->submission->id])
            ->assertStatus(200)
            ->assertDontSee('Setujui RKAP')
            ->assertDontSee('Minta Revisi');
    }

    public function test_direksi_can_see_approval_buttons_in_dir_review_status(): void
    {
        // Update submission to dir_review
        $this->submission->update(['status' => 'dir_review']);

        $this->actingAs($this->direksi);

        Livewire::test(RkapApprovalReview::class, ['id' => $this->submission->id])
            ->assertStatus(200)
            ->assertSee('Setujui RKAP')
            ->assertSee('Minta Revisi');
    }

    public function test_verifikator_can_see_approval_buttons_in_final_review_status(): void
    {
        // Update submission to final_review
        $this->submission->update(['status' => 'final_review']);

        $this->actingAs($this->verifikator);

        Livewire::test(RkapApprovalReview::class, ['id' => $this->submission->id])
            ->assertStatus(200)
            ->assertSee('Setujui RKAP')
            ->assertSee('Minta Revisi');
    }

    public function test_verifikator_approving_transitions_to_pdir_review(): void
    {
        $this->submission->update(['status' => 'final_review']);

        $this->actingAs($this->verifikator);

        Livewire::test(RkapApprovalReview::class, ['id' => $this->submission->id])
            ->call('approve');

        $this->assertEquals('pdir_review', $this->submission->fresh()->status);
    }

    public function test_president_director_can_see_approval_buttons_in_pdir_review_status(): void
    {
        $this->submission->update(['status' => 'pdir_review']);

        $this->actingAs($this->president);

        Livewire::test(RkapApprovalReview::class, ['id' => $this->submission->id])
            ->assertStatus(200)
            ->assertSee('Setujui RKAP')
            ->assertSee('Minta Revisi');
    }

    public function test_president_director_approving_leaves_in_pdir_review_if_finance_pending(): void
    {
        $this->submission->update(['status' => 'pdir_review']);

        $this->actingAs($this->president);

        Livewire::test(RkapApprovalReview::class, ['id' => $this->submission->id])
            ->call('approve');

        // Status must remain pdir_review because Finance Director has not approved
        $this->assertEquals('pdir_review', $this->submission->fresh()->status);
    }

    public function test_parallel_final_approvals_transition_to_approved(): void
    {
        $this->submission->update(['status' => 'pdir_review']);

        // Create Finance Director
        $dirFinanceRole = Role::firstOrCreate(['name' => 'direksi']);
        $financeDirectorate = Directorate::create([
            'code' => 'HF',
            'name' => 'Finance Directorate',
            'is_active' => true,
        ]);
        $financeUser = User::create([
            'name' => 'Finance Director',
            'email' => 'finance_dir@example.com',
            'password' => bcrypt('password'),
            'directorate_id' => $financeDirectorate->id,
        ]);
        $financeUser->assignRole($dirFinanceRole);

        // 1. Finance Director approves
        $this->actingAs($financeUser);
        Livewire::test(RkapApprovalReview::class, ['id' => $this->submission->id])
            ->call('approve');

        $this->assertEquals('pdir_review', $this->submission->fresh()->status);

        // 2. President Director approves
        $this->actingAs($this->president);
        Livewire::test(RkapApprovalReview::class, ['id' => $this->submission->id])
            ->call('approve');

        // Now both have approved, status must be approved
        $this->assertEquals('approved', $this->submission->fresh()->status);
    }

    public function test_president_director_requesting_revision_transitions_to_draft(): void
    {
        $this->submission->update(['status' => 'pdir_review']);

        $wp = \App\Models\RkapWorkPlan::create([
            'rkap_submission_id' => $this->submission->id,
            'program_code' => 'PROG01',
            'program_name' => 'Program Test',
            'description' => 'Test',
            'quantity' => 1,
            'unit' => 'Paket',
            'approval_status' => 'pending',
        ]);

        $this->actingAs($this->president);

        Livewire::test(RkapApprovalReview::class, ['id' => $this->submission->id])
            ->set('activityStatuses.' . $wp->id, 'rejected')
            ->set('activityRevisionNotes.' . $wp->id, 'Need more details on training expenses.')
            ->set('revisionReason', 'Need more details on training expenses.')
            ->call('requestRevision');

        $this->assertEquals('draft', $this->submission->fresh()->status);

        // Ensure approval log has the revision reason and role
        $latestApproval = $this->submission->approvals()->first();
        $this->assertEquals('direktur_utama', $latestApproval->role);
        $this->assertEquals('revision_requested', $latestApproval->action);
        $this->assertEquals('Need more details on training expenses.', $latestApproval->comments);
    }

    public function test_finance_director_requesting_revision_transitions_to_draft(): void
    {
        $this->submission->update(['status' => 'pdir_review']);

        $wp = \App\Models\RkapWorkPlan::create([
            'rkap_submission_id' => $this->submission->id,
            'program_code' => 'PROG01',
            'program_name' => 'Program Test',
            'description' => 'Test',
            'quantity' => 1,
            'unit' => 'Paket',
            'approval_status' => 'pending',
        ]);

        // Create Finance Director
        $dirFinanceRole = Role::firstOrCreate(['name' => 'direksi']);
        $financeDirectorate = Directorate::create([
            'code' => 'HF',
            'name' => 'Finance Directorate',
            'is_active' => true,
        ]);
        $financeUser = User::create([
            'name' => 'Finance Director',
            'email' => 'finance_dir@example.com',
            'password' => bcrypt('password'),
            'directorate_id' => $financeDirectorate->id,
        ]);
        $financeUser->assignRole($dirFinanceRole);

        $this->actingAs($financeUser);

        Livewire::test(RkapApprovalReview::class, ['id' => $this->submission->id])
            ->set('activityStatuses.' . $wp->id, 'rejected')
            ->set('activityRevisionNotes.' . $wp->id, 'Budget allocation for travel is too high.')
            ->set('revisionReason', 'Budget allocation for travel is too high.')
            ->call('requestRevision');

        $this->assertEquals('draft', $this->submission->fresh()->status);

        $latestApproval = $this->submission->approvals()->first();
        $this->assertEquals('direktur_keuangan', $latestApproval->role);
        $this->assertEquals('revision_requested', $latestApproval->action);
        $this->assertEquals('Budget allocation for travel is too high.', $latestApproval->comments);
    }

    public function test_approver_sees_inline_revision_changes_on_review(): void
    {
        $this->actingAs($this->creator);

        // 1. Setup initial activity & budget item
        $wp = \App\Models\RkapWorkPlan::create([
            'rkap_submission_id' => $this->submission->id,
            'program_code' => 'PROG01',
            'program_name' => 'Program Test',
            'description' => 'Test V1',
            'quantity' => 1,
            'unit' => 'Paket',
            'approval_status' => 'pending',
            'sort_order' => 1,
        ]);

        $bi = \App\Models\RkapBudgetItem::create([
            'rkap_work_plan_id' => $wp->id,
            'account_code' => '510101',
            'description' => 'Travel Expense',
            'quantity' => 1,
            'unit' => 'Pax',
            'unit_price' => 1000000,
            'total_price' => 1000000,
        ]);

        // Capture Version 1 snapshot
        $this->submission->load('workPlans.budgetItems.monthlies', 'workPlans.budgetItems.cashOuts');
        $this->submission->createVersion('initial', 'Pengajuan Awal');

        // 2. Simulate revision changes (version 2)
        $this->submission->increment('current_version');

        // Edit description
        $wp->update(['description' => 'Test V2']);

        // Modify travel expense quantity from 1 to 2
        $bi->update([
            'quantity' => 2,
            'total_price' => 2000000,
        ]);

        // Add a new budget item
        $newBi = \App\Models\RkapBudgetItem::create([
            'rkap_work_plan_id' => $wp->id,
            'account_code' => '510102',
            'description' => 'Accommodation',
            'quantity' => 1,
            'unit' => 'Night',
            'unit_price' => 500000,
            'total_price' => 500000,
        ]);

        // Capture Version 2 snapshot
        $this->submission->load('workPlans.budgetItems.monthlies', 'workPlans.budgetItems.cashOuts');
        $this->submission->createVersion('revision', 'Pengisian Revisi');

        // 3. Act as reviewer and load the Livewire component
        $this->actingAs($this->kadept);

        Livewire::test(RkapApprovalReview::class, ['id' => $this->submission->id])
            ->assertSet('submission.current_version', 2)
            ->assertSet('prevVersionSnapshot', $this->submission->versions->firstWhere('version_number', 1)->snapshot_data)
            ->assertViewHas('revisionChanges', function ($changes) use ($wp, $bi, $newBi) {
                $actChanges = $changes[$wp->id] ?? null;
                if (!$actChanges || !$actChanges['has_changes']) {
                    return false;
                }

                // Check description change registered
                $descChange = collect($actChanges['activity_level_changes'])->firstWhere('field', 'Deskripsi / Tujuan');
                if (!$descChange || $descChange['old'] !== 'Test V1' || $descChange['new'] !== 'Test V2') {
                    return false;
                }

                // Check new budget item added
                $added = collect($actChanges['added_items'])->firstWhere('account_code', '510102');
                if (!$added || $added['description'] !== 'Accommodation') {
                    return false;
                }

                // Check modified budget item
                $modified = collect($actChanges['modified_items'])->firstWhere('id', $bi->id);
                if (!$modified || $modified['old']['quantity'] != 1 || $modified['new']['quantity'] != 2) {
                    return false;
                }

                return true;
            });
    }

    public function test_open_revision_form_with_empty_notes_fails_and_dispatches_event(): void
    {
        $this->submission->update(['status' => 'pdir_review']);

        $wp = \App\Models\RkapWorkPlan::create([
            'rkap_submission_id' => $this->submission->id,
            'program_code' => 'PROG01',
            'program_name' => 'Program Test',
            'description' => 'Test',
            'quantity' => 1,
            'unit' => 'Paket',
            'approval_status' => 'pending',
        ]);

        $this->actingAs($this->president);

        Livewire::test(RkapApprovalReview::class, ['id' => $this->submission->id])
            ->set('activityStatuses.' . $wp->id, 'rejected')
            ->set('activityRevisionNotes.' . $wp->id, '')
            ->call('openRevisionForm')
            ->assertDispatched('focus-activity-revision-note', id: $wp->id);
    }

    public function test_request_revision_with_empty_notes_fails_and_dispatches_event(): void
    {
        $this->submission->update(['status' => 'pdir_review']);

        $wp = \App\Models\RkapWorkPlan::create([
            'rkap_submission_id' => $this->submission->id,
            'program_code' => 'PROG01',
            'program_name' => 'Program Test',
            'description' => 'Test',
            'quantity' => 1,
            'unit' => 'Paket',
            'approval_status' => 'pending',
        ]);

        $this->actingAs($this->president);

        Livewire::test(RkapApprovalReview::class, ['id' => $this->submission->id])
            ->set('activityStatuses.' . $wp->id, 'rejected')
            ->set('activityRevisionNotes.' . $wp->id, '')
            ->call('requestRevision')
            ->assertDispatched('focus-activity-revision-note', id: $wp->id);
    }

    public function test_verifikator_can_add_activity_with_mapped_coas_during_review(): void
    {
        $this->submission->update(['status' => 'final_review']);

        $workPlan = \App\Models\WorkPlan::create([
            'code' => 'WP-TEST',
            'title' => 'Work Plan Test',
        ]);

        $activity = \App\Models\Activity::create([
            'work_plan_id' => $workPlan->id,
            'code' => 'ACT-TEST',
            'title' => 'Activity Test',
            'description' => 'Activity Test Description',
        ]);

        $coa = \App\Models\Coa::create([
            'code' => 'COA-TEST',
            'title' => 'Coa Test',
            'description' => 'Coa Test Description',
        ]);

        $activity->coas()->sync([$coa->id]);

        $this->actingAs($this->verifikator);

        Livewire::test(RkapApprovalReview::class, ['id' => $this->submission->id])
            ->assertSee('Program Kegiatan')
            ->call('enterEditMode')
            ->assertSet('isEditMode', true)
            ->set('selectedWorkPlanId', $workPlan->id)
            ->set('selectedActivityId', $activity->id)
            ->assertSet('activityDescription', 'Activity Test Description')
            ->set('activityQuantity', 2)
            ->set('activityUnit', 'Kali')
            ->set('activityOutputTarget', 'Target Test')
            ->set('newActivityBudgetItems.0.quantity', 3)
            ->set('newActivityBudgetItems.0.unit', 'Pcs')
            ->set('newActivityBudgetItems.0.unit_price', 10000)
            ->set('newActivityBudgetItems.0.remarks', 'Budget Remark')
            ->call('saveEditMode')
            ->assertHasNoErrors()
            ->assertSet('isEditMode', false);

        // Verify it was added to database
        $this->assertDatabaseHas('rkap_work_plans', [
            'rkap_submission_id' => $this->submission->id,
            'activity_id' => $activity->id,
            'program_code' => 'ACT-TEST',
            'program_name' => 'Activity Test',
            'quantity' => 2,
            'unit' => 'Kali',
        ]);

        $rkapWorkPlan = \App\Models\RkapWorkPlan::where('rkap_submission_id', $this->submission->id)
            ->where('activity_id', $activity->id)
            ->first();

        $this->assertDatabaseHas('rkap_budget_items', [
            'rkap_work_plan_id' => $rkapWorkPlan->id,
            'account_code' => 'COA-TEST',
            'description' => 'Coa Test',
            'quantity' => 3,
            'unit' => 'Pcs',
            'unit_price' => 10000.00,
            'total_price' => 30000.00,
            'remarks' => 'Budget Remark',
        ]);

        // Monthly subtotal checks (should be distributed evenly)
        $budgetItem = \App\Models\RkapBudgetItem::where('rkap_work_plan_id', $rkapWorkPlan->id)->first();
        $this->assertEquals(12, $budgetItem->monthlies()->count());
        $this->assertEquals(2500.00, $budgetItem->monthlies()->first()->amount);
    }

    public function test_non_verifikator_cannot_see_add_activity_button(): void
    {
        $this->submission->update(['status' => 'final_review']);

        // Login as non-verifikator, e.g. kadept
        $this->actingAs($this->kadept);

        Livewire::test(RkapApprovalReview::class, ['id' => $this->submission->id])
            ->assertDontSee('Tambah Program Kegiatan');
    }

    public function test_verifikator_can_delete_own_added_activity(): void
    {
        $this->submission->update(['status' => 'final_review', 'total_budget' => 0]);

        $workPlan = \App\Models\WorkPlan::create(['code' => 'WP-DEL', 'title' => 'WP Del']);
        $activity = \App\Models\Activity::create(['work_plan_id' => $workPlan->id, 'code' => 'ACT-DEL', 'title' => 'Act Del']);
        $coa = \App\Models\Coa::create(['code' => 'COA-DEL', 'title' => 'Coa Del']);
        $activity->coas()->sync([$coa->id]);

        // 1. Add activity
        $this->actingAs($this->verifikator);
        Livewire::test(RkapApprovalReview::class, ['id' => $this->submission->id])
            ->call('enterEditMode')
            ->set('selectedWorkPlanId', $workPlan->id)
            ->set('selectedActivityId', $activity->id)
            ->set('newActivityBudgetItems.0.quantity', 1)
            ->set('newActivityBudgetItems.0.unit', 'Pcs')
            ->set('newActivityBudgetItems.0.unit_price', 1000)
            ->call('saveEditMode')
            ->assertHasNoErrors();

        $wp = \App\Models\RkapWorkPlan::where('rkap_submission_id', $this->submission->id)
            ->where('activity_id', $activity->id)
            ->firstOrFail();

        $this->assertTrue($wp->added_by_verifier);
        $this->assertEquals(1000.00, $this->submission->fresh()->total_budget);

        // 2. Delete activity in a fresh test instance to preserve authentication state
        $this->actingAs($this->verifikator);
        Livewire::test(RkapApprovalReview::class, ['id' => $this->submission->id])
            ->call('deleteWorkPlan', $wp->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('rkap_work_plans', ['id' => $wp->id]);
        $this->assertDatabaseMissing('rkap_budget_items', ['rkap_work_plan_id' => $wp->id]);
        $this->assertEquals(0.00, $this->submission->fresh()->total_budget);
    }

    public function test_verifikator_can_delete_own_added_budget_item(): void
    {
        $this->submission->update(['status' => 'final_review', 'total_budget' => 0]);

        $workPlan = \App\Models\WorkPlan::create(['code' => 'WP-DEL-BI', 'title' => 'WP Del BI']);
        $activity = \App\Models\Activity::create(['work_plan_id' => $workPlan->id, 'code' => 'ACT-DEL-BI', 'title' => 'Act Del BI']);
        $coa1 = \App\Models\Coa::create(['code' => 'COA-DEL-BI1', 'title' => 'Coa Del BI1']);
        $coa2 = \App\Models\Coa::create(['code' => 'COA-DEL-BI2', 'title' => 'Coa Del BI2']);
        $activity->coas()->sync([$coa1->id, $coa2->id]);

        // 1. Add activity
        $this->actingAs($this->verifikator);
        Livewire::test(RkapApprovalReview::class, ['id' => $this->submission->id])
            ->call('enterEditMode')
            ->set('selectedWorkPlanId', $workPlan->id)
            ->set('selectedActivityId', $activity->id)
            ->set('newActivityBudgetItems.0.quantity', 1)
            ->set('newActivityBudgetItems.0.unit', 'Pcs')
            ->set('newActivityBudgetItems.0.unit_price', 1000)
            ->set('newActivityBudgetItems.1.quantity', 1)
            ->set('newActivityBudgetItems.1.unit', 'Pcs')
            ->set('newActivityBudgetItems.1.unit_price', 2000)
            ->call('saveEditMode')
            ->assertHasNoErrors();

        $wp = \App\Models\RkapWorkPlan::where('rkap_submission_id', $this->submission->id)
            ->where('activity_id', $activity->id)
            ->firstOrFail();

        $this->assertEquals(3000.00, $this->submission->fresh()->total_budget);

        $bi1 = \App\Models\RkapBudgetItem::where('rkap_work_plan_id', $wp->id)->where('account_code', 'COA-DEL-BI1')->firstOrFail();
        $bi2 = \App\Models\RkapBudgetItem::where('rkap_work_plan_id', $wp->id)->where('account_code', 'COA-DEL-BI2')->firstOrFail();

        // 2. Delete budget item in a fresh test instance
        $this->actingAs($this->verifikator);
        Livewire::test(RkapApprovalReview::class, ['id' => $this->submission->id])
            ->call('deleteBudgetItem', $bi1->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('rkap_budget_items', ['id' => $bi1->id]);
        $this->assertDatabaseHas('rkap_budget_items', ['id' => $bi2->id]);
        $this->assertEquals(2000.00, $this->submission->fresh()->total_budget);
    }

    public function test_verifikator_cannot_delete_original_activity_or_budget_item(): void
    {
        $this->submission->update(['status' => 'final_review']);

        $wp = \App\Models\RkapWorkPlan::create([
            'rkap_submission_id' => $this->submission->id,
            'program_code' => 'PROG-ORIG',
            'program_name' => 'Original Program',
            'quantity' => 1,
            'unit' => 'Paket',
            'added_by_verifier' => false, // Added by department originally
        ]);

        $bi = \App\Models\RkapBudgetItem::create([
            'rkap_work_plan_id' => $wp->id,
            'account_code' => 'COA-ORIG',
            'description' => 'Original Item',
            'quantity' => 1,
            'unit' => 'Paket',
            'unit_price' => 5000,
            'total_price' => 5000,
        ]);

        $this->actingAs($this->verifikator);

        // Expect ModelNotFoundException since query filters by added_by_verifier = true
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Livewire::test(RkapApprovalReview::class, ['id' => $this->submission->id])
            ->call('deleteWorkPlan', $wp->id);
    }

    public function test_non_verifikator_cannot_delete_added_activity(): void
    {
        $this->submission->update(['status' => 'final_review']);

        $wp = \App\Models\RkapWorkPlan::create([
            'rkap_submission_id' => $this->submission->id,
            'program_code' => 'PROG-ADD',
            'program_name' => 'Added Program',
            'quantity' => 1,
            'unit' => 'Paket',
            'added_by_verifier' => true,
        ]);

        $this->actingAs($this->kadept);

        Livewire::test(RkapApprovalReview::class, ['id' => $this->submission->id])
            ->call('deleteWorkPlan', $wp->id);

        $this->assertDatabaseHas('rkap_work_plans', ['id' => $wp->id]);
    }

    public function test_verifikator_can_change_coa_of_existing_budget_item_in_edit_mode(): void
    {
        $this->submission->update(['status' => 'final_review']);

        $wp = \App\Models\RkapWorkPlan::create([
            'rkap_submission_id' => $this->submission->id,
            'program_code' => 'PROG-ORIG',
            'program_name' => 'Original Program',
            'quantity' => 1,
            'unit' => 'Paket',
            'added_by_verifier' => false,
        ]);

        $bi = \App\Models\RkapBudgetItem::create([
            'rkap_work_plan_id' => $wp->id,
            'account_code' => 'COA-OLD',
            'description' => 'Original Item',
            'quantity' => 1,
            'unit' => 'Paket',
            'unit_price' => 5000,
            'total_price' => 5000,
        ]);

        $newCoa = \App\Models\Coa::create([
            'code' => 'COA-NEW',
            'title' => 'New Coa',
            'description' => 'New Coa Description',
        ]);

        $this->actingAs($this->verifikator);

        Livewire::test(RkapApprovalReview::class, ['id' => $this->submission->id])
            ->call('enterEditMode')
            ->assertSet('isEditMode', true)
            ->set('editCoas.' . $bi->id, 'COA-NEW')
            ->call('saveEditMode')
            ->assertHasNoErrors()
            ->assertSet('isEditMode', false);

        $this->assertEquals('COA-NEW', $bi->fresh()->account_code);
    }

    public function test_approver_can_approve_all_activities(): void
    {
        $wp1 = \App\Models\RkapWorkPlan::create([
            'rkap_submission_id' => $this->submission->id,
            'program_code' => 'P1',
            'program_name' => 'Program 1',
            'quantity' => 1,
            'approval_status' => 'pending',
        ]);
        $wp2 = \App\Models\RkapWorkPlan::create([
            'rkap_submission_id' => $this->submission->id,
            'program_code' => 'P2',
            'program_name' => 'Program 2',
            'quantity' => 1,
            'approval_status' => 'pending',
        ]);

        $this->actingAs($this->kadept);

        Livewire::test(RkapApprovalReview::class, ['id' => $this->submission->id])
            ->call('approveAllActivities')
            ->assertHasNoErrors();

        $this->assertEquals('approved', $wp1->fresh()->approval_status);
        $this->assertEquals('approved', $wp2->fresh()->approval_status);
    }

    public function test_approver_can_reject_all_activities(): void
    {
        $wp1 = \App\Models\RkapWorkPlan::create([
            'rkap_submission_id' => $this->submission->id,
            'program_code' => 'P1',
            'program_name' => 'Program 1',
            'quantity' => 1,
            'approval_status' => 'pending',
        ]);
        $wp2 = \App\Models\RkapWorkPlan::create([
            'rkap_submission_id' => $this->submission->id,
            'program_code' => 'P2',
            'program_name' => 'Program 2',
            'quantity' => 1,
            'approval_status' => 'pending',
        ]);

        $this->actingAs($this->kadept);

        Livewire::test(RkapApprovalReview::class, ['id' => $this->submission->id])
            ->call('rejectAllActivities')
            ->assertHasNoErrors();

        $this->assertEquals('rejected', $wp1->fresh()->approval_status);
        $this->assertEquals('rejected', $wp2->fresh()->approval_status);
    }
}

