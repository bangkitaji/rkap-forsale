<?php

namespace Tests\Feature;

use Tests\TestCase;
use Livewire\Livewire;
use App\Livewire\Rkap\RkapPeriodClosingManagement;
use App\Livewire\Rkap\RkapRealizationUpload;
use App\Livewire\Rkap\RkapProjections;
use App\Livewire\Rkap\RkapProjectionUpload;
use App\Models\RkapPeriod;
use App\Models\RkapBudgetItem;
use App\Models\RkapBudgetItemRealization;
use App\Models\RkapBudgetItemProjection;
use App\Models\RkapSubmission;
use App\Models\Bureau;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\User;
use App\Models\WorkPlan;
use App\Models\RkapWorkPlan;
use App\Models\Setting;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RkapPeriodClosingTest extends TestCase
{
    use RefreshDatabase;

    protected User $verifikator;
    protected RkapPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();
        \Carbon\Carbon::setTestNow('2026-07-04 15:00:00');

        // Create roles and permissions
        $roleVerifikator = Role::firstOrCreate(['name' => 'verifikator']);
        $permRealizationUpload = Permission::firstOrCreate(['name' => 'rkap.realization.upload', 'guard_name' => 'web']);
        $permClosingManage = Permission::firstOrCreate(['name' => 'rkap.closing.manage', 'guard_name' => 'web']);
        $permProjectionInput = Permission::firstOrCreate(['name' => 'rkap.projection.input', 'guard_name' => 'web']);
        $permProjectionView = Permission::firstOrCreate(['name' => 'rkap.projection.view', 'guard_name' => 'web']);
        
        $roleVerifikator->givePermissionTo($permRealizationUpload);
        $roleVerifikator->givePermissionTo($permClosingManage);
        $roleVerifikator->givePermissionTo($permProjectionInput);
        $roleVerifikator->givePermissionTo($permProjectionView);

        $this->verifikator = User::create([
            'name' => 'Verifikator User',
            'email' => 'verifikator@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->verifikator->assignRole($roleVerifikator);

        $this->period = RkapPeriod::create([
            'year' => (int) date('Y'),
            'title' => 'RKAP ' . date('Y'),
            'status' => 'finalized',
            'submission_start' => now()->subDay(),
            'submission_end' => now()->addDay(),
        ]);
    }

    public function test_unauthorized_user_cannot_access_closing_period(): void
    {
        $user = User::create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user);

        Livewire::test(RkapPeriodClosingManagement::class)
            ->assertStatus(403);
    }

    public function test_authorized_user_can_access_and_save_closing_day(): void
    {
        Livewire::actingAs($this->verifikator)
            ->test(RkapPeriodClosingManagement::class)
            ->assertStatus(200)
            ->set('closingDay', 15)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Setting closing periode berhasil disimpan.');

        $this->assertEquals(15, Setting::get('rkap_closing_day'));
    }

    protected function tearDown(): void
    {
        \Carbon\Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dropdown_month_hides_closed_month_in_realization_upload(): void
    {
        // Set closing day to 1 (meaning the next month's 1st is the closing deadline)
        // Today is 2026-07-04 (from system metadata context).
        // For Juni (6), closing deadline is 2026-07-01. So Juni (6) is closed.
        // For Juli (7), closing deadline is 2026-08-01. So Juli (7) is open.
        Setting::set('rkap_closing_day', 1);

        $component = Livewire::actingAs($this->verifikator)
            ->test(RkapRealizationUpload::class)
            ->set('periodId', $this->period->id);

        $monthOptions = $component->get('monthOptions');
        
        // Month 6 (Juni) should be closed and thus excluded
        $this->assertArrayNotHasKey(6, $monthOptions);

        // Month 7 (Juli) should be open and thus included
        $this->assertArrayHasKey(7, $monthOptions);
    }

    public function test_upload_realization_fails_if_month_is_closed(): void
    {
        Setting::set('rkap_closing_day', 1); // Juni (6) is closed as of July 4th

        $file = \Illuminate\Http\UploadedFile::fake()->create('realisasi.xlsx', 100);

        $component = Livewire::actingAs($this->verifikator)
            ->test(RkapRealizationUpload::class)
            ->set('periodId', $this->period->id)
            ->set('month', 6) // Juni (closed)
            ->set('file', $file)
            ->call('uploadAndImport');

        $errors = $component->get('errorsList');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('telah ditutup karena melewati batas closing periode', $errors[0]);
    }

    public function test_delete_realization_fails_if_month_is_closed(): void
    {
        // Set up required entities to delete a realization
        $directorate = Directorate::create(['code' => 'D1', 'name' => 'Dir 1']);
        $department = Department::create(['directorate_id' => $directorate->id, 'code' => 'DP1', 'name' => 'Dept 1']);
        $bureau = Bureau::create(['department_id' => $department->id, 'code' => 'B1', 'name' => 'Bur 1']);
        
        $submission = RkapSubmission::create([
            'rkap_period_id' => $this->period->id,
            'bureau_id' => $bureau->id,
            'created_by' => $this->verifikator->id,
            'status' => 'approved',
            'total_budget' => 100000,
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

        $budgetItem = RkapBudgetItem::create([
            'rkap_work_plan_id' => $workPlan->id,
            'account_code' => '521111',
            'description' => 'Target Item Description',
            'quantity' => 1,
            'unit_price' => 10000,
        ]);

        $realization = RkapBudgetItemRealization::create([
            'rkap_budget_item_id' => $budgetItem->id,
            'rkap_period_id' => $this->period->id,
            'month' => 6, // Juni
            'amount' => 7500,
            'uploaded_by' => $this->verifikator->id,
            'uploaded_at' => now(),
        ]);

        Setting::set('rkap_closing_day', 1); // Juni (6) is closed as of July 4th

        $component = Livewire::actingAs($this->verifikator)
            ->test(RkapRealizationUpload::class)
            ->set('periodId', $this->period->id)
            ->call('deleteRealization', $realization->id)
            ->assertSee('tidak dapat dihapus karena periode pengisian realisasi telah ditutup');

        // Verify the database record is NOT deleted
        $this->assertDatabaseHas('rkap_budget_item_realizations', [
            'id' => $realization->id,
        ]);
    }

    public function test_template_download_fails_if_month_is_closed(): void
    {
        $this->actingAs($this->verifikator);

        Setting::set('rkap_closing_day', 1); // Juni (6) is closed as of July 4th

        // Excel template download for month 6 (Juni)
        $responseExcel = $this->get(route('rkap-realization-template-download', [
            'period_id' => $this->period->id,
            'month' => 6
        ]));
        $responseExcel->assertStatus(403);

        // CSV template download for month 6 (Juni)
        $responseCsv = $this->get(route('rkap-realization-template-download-csv', [
            'period_id' => $this->period->id,
            'month' => 6
        ]));
        $responseCsv->assertStatus(403);

        // Excel template download for month 7 (Juli) which is open
        $responseOpenExcel = $this->get(route('rkap-realization-template-download', [
            'period_id' => $this->period->id,
            'month' => 7
        ]));
        $responseOpenExcel->assertStatus(200);
    }

    public function test_projection_input_fails_if_month_is_closed(): void
    {

        $directorate = Directorate::create(['code' => 'D1', 'name' => 'Dir 1']);
        $department = Department::create(['directorate_id' => $directorate->id, 'code' => 'DP1', 'name' => 'Dept 1']);
        $bureau = Bureau::create(['department_id' => $department->id, 'code' => 'B1', 'name' => 'Bur 1']);
        
        $submission = RkapSubmission::create([
            'rkap_period_id' => $this->period->id,
            'bureau_id' => $bureau->id,
            'created_by' => $this->verifikator->id,
            'status' => 'approved',
            'total_budget' => 100000,
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

        $budgetItem = RkapBudgetItem::create([
            'rkap_work_plan_id' => $workPlan->id,
            'account_code' => '521111',
            'description' => 'Target Item Description',
            'quantity' => 1,
            'unit_price' => 10000,
        ]);

        $projection = RkapBudgetItemProjection::create([
            'rkap_budget_item_id' => $budgetItem->id,
            'rkap_period_id' => $this->period->id,
            'month' => 6, // Juni
            'amount' => 5000,
            'inputted_by' => $this->verifikator->id,
        ]);

        Setting::set('rkap_closing_day', 1); // Juni (6) is closed as of July 4th

        Livewire::actingAs($this->verifikator)
            ->test(RkapProjections::class)
            ->call('selectBudgetItem', $budgetItem->id)
            ->set('editingProjections.6', 6000) // Change month 6 projection amount
            ->call('saveMonthlyProjections')
            ->assertHasErrors(['editingProjections.6']);
    }

    public function test_mass_upload_projection_fails_if_month_is_closed(): void
    {

        $directorate = Directorate::create(['code' => 'D1', 'name' => 'Dir 1']);
        $department = Department::create(['directorate_id' => $directorate->id, 'code' => 'DP1', 'name' => 'Dept 1']);
        $bureau = Bureau::create(['department_id' => $department->id, 'code' => 'B1', 'name' => 'Bur 1']);
        
        $submission = RkapSubmission::create([
            'rkap_period_id' => $this->period->id,
            'bureau_id' => $bureau->id,
            'created_by' => $this->verifikator->id,
            'status' => 'approved',
            'total_budget' => 100000,
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

        $budgetItem = RkapBudgetItem::create([
            'rkap_work_plan_id' => $workPlan->id,
            'account_code' => '521111',
            'description' => 'Target Item Description',
            'quantity' => 1,
            'unit_price' => 10000,
        ]);

        RkapBudgetItemProjection::create([
            'rkap_budget_item_id' => $budgetItem->id,
            'rkap_period_id' => $this->period->id,
            'month' => 6, // Juni
            'amount' => 5000,
            'inputted_by' => $this->verifikator->id,
        ]);

        Setting::set('rkap_closing_day', 1); // Juni (6) is closed as of July 4th

        $csvContent = "budget_item_id,yearly,m1,m2,m3,m4,m5,m6,m7,m8,m9,m10,m11,m12\n{$budgetItem->id},0,0,0,0,0,0,6000,0,0,0,0,0,0";
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('proyeksi.csv', $csvContent);

        $component = Livewire::actingAs($this->verifikator)
            ->test(RkapProjectionUpload::class)
            ->set('periodId', $this->period->id)
            ->set('file', $file)
            ->call('uploadAndImport');

        $errors = $component->get('errorsList');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('periode pengisian telah ditutup', $errors[0]);
    }

    public function test_yearly_projection_input_fails_if_month_is_closed(): void
    {
        $directorate = Directorate::create(['code' => 'D1', 'name' => 'Dir 1']);
        $department = Department::create(['directorate_id' => $directorate->id, 'code' => 'DP1', 'name' => 'Dept 1']);
        $bureau = Bureau::create(['department_id' => $department->id, 'code' => 'B1', 'name' => 'Bur 1']);
        
        $submission = RkapSubmission::create([
            'rkap_period_id' => $this->period->id,
            'bureau_id' => $bureau->id,
            'created_by' => $this->verifikator->id,
            'status' => 'approved',
            'total_budget' => 100000,
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

        $budgetItem = RkapBudgetItem::create([
            'rkap_work_plan_id' => $workPlan->id,
            'account_code' => '521111',
            'description' => 'Target Item Description',
            'quantity' => 1,
            'unit_price' => 10000,
            'projection' => 5000, // Initial yearly projection
        ]);

        Setting::set('rkap_closing_day', 1); // Juni (6) is closed as of July 4th

        Livewire::actingAs($this->verifikator)
            ->test(RkapProjections::class)
            ->call('selectBudgetItem', $budgetItem->id)
            ->set('inputMode', 'yearly')
            ->set('yearlyProjection', 6000) // Change yearly projection amount
            ->call('saveMonthlyProjections')
            ->assertHasErrors(['yearlyProjection']);
    }

    public function test_projection_save_succeeds_for_closed_month_with_realization_but_no_projection(): void
    {
        $directorate = Directorate::create(['code' => 'D2', 'name' => 'Dir 2']);
        $department = Department::create(['directorate_id' => $directorate->id, 'code' => 'DP2', 'name' => 'Dept 2']);
        $bureau = Bureau::create(['department_id' => $department->id, 'code' => 'B2', 'name' => 'Bur 2']);

        $submission = RkapSubmission::create([
            'rkap_period_id' => $this->period->id,
            'bureau_id' => $bureau->id,
            'created_by' => $this->verifikator->id,
            'status' => 'approved',
            'total_budget' => 100000,
        ]);

        $wpMaster = WorkPlan::create([
            'code' => 'WP002',
            'title' => 'Work Plan 2',
        ]);

        $workPlan = RkapWorkPlan::create([
            'rkap_submission_id' => $submission->id,
            'work_plan_id' => $wpMaster->id,
            'program_code' => 'WP002',
            'program_name' => 'Work Plan 2',
        ]);

        $budgetItem = RkapBudgetItem::create([
            'rkap_work_plan_id' => $workPlan->id,
            'account_code' => '521222',
            'description' => 'Item With Realization',
            'quantity' => 1,
            'unit_price' => 100000,
            'total_price' => 100000,
        ]);

        // Add realization for month 6 (Juni) — but NO projection exists yet
        RkapBudgetItemRealization::create([
            'rkap_budget_item_id' => $budgetItem->id,
            'rkap_period_id' => $this->period->id,
            'month' => 6,
            'amount' => 15000,
            'uploaded_by' => $this->verifikator->id,
            'uploaded_at' => now(),
        ]);

        Setting::set('rkap_closing_day', 1); // Juni (6) is closed as of July 4th

        // User opens the projection modal — month 6 should be initialized to the realization amount (15000)
        $component = Livewire::actingAs($this->verifikator)
            ->test(RkapProjections::class)
            ->call('selectBudgetItem', $budgetItem->id)
            ->assertSet('editingProjections.6', 15000);

        // Set all other months to 0 so total doesn't exceed budget
        for ($m = 1; $m <= 12; $m++) {
            if ($m !== 6) {
                $component->set('editingProjections.' . $m, 0);
            }
        }

        // Saving should succeed — closed month with realization should not block save
        $component
            ->call('saveMonthlyProjections')
            ->assertHasNoErrors()
            ->assertDispatched('projections-saved');

        // Verify the projection was saved with the realization amount
        $savedProjection = RkapBudgetItemProjection::where('rkap_budget_item_id', $budgetItem->id)
            ->where('month', 6)
            ->first();

        $this->assertNotNull($savedProjection);
        $this->assertEquals(15000, (float) $savedProjection->amount);
    }

    public function test_admin_can_update_projection_status_setting(): void
    {
        Livewire::actingAs($this->verifikator)
            ->test(RkapPeriodClosingManagement::class)
            ->assertSet('projectionStatus', 'open')
            ->set('projectionStatus', 'closed')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals('closed', Setting::get('rkap_projection_status'));
    }

    public function test_closed_projection_setting_prevents_saving_projection(): void
    {
        Setting::set('rkap_projection_status', 'closed');

        $directorate = Directorate::create(['name' => 'Dir Test', 'code' => 'DIR']);
        $department = Department::create(['name' => 'Dept Test', 'code' => 'DEPT', 'directorate_id' => $directorate->id]);
        $bureau = Bureau::create(['name' => 'Bureau Test', 'code' => 'BUR', 'department_id' => $department->id]);
        $wpMaster = WorkPlan::create(['title' => 'WP Master', 'code' => 'WPM']);

        $submission = RkapSubmission::create([
            'rkap_period_id' => $this->period->id,
            'bureau_id' => $bureau->id,
            'status' => 'approved',
            'created_by' => $this->verifikator->id,
        ]);

        $workPlan = RkapWorkPlan::create([
            'rkap_submission_id' => $submission->id,
            'work_plan_id' => $wpMaster->id,
            'program_code' => 'WP003',
            'program_name' => 'Work Plan 3',
        ]);

        $budgetItem = RkapBudgetItem::create([
            'rkap_work_plan_id' => $workPlan->id,
            'account_code' => '521223',
            'description' => 'Item Projection Test',
            'quantity' => 1,
            'unit_price' => 100000,
            'total_price' => 100000,
        ]);

        Livewire::actingAs($this->verifikator)
            ->test(RkapProjections::class)
            ->call('selectBudgetItem', $budgetItem->id)
            ->set('editingProjections.7', 50000)
            ->call('saveMonthlyProjections')
            ->assertSee('Penginputan dan perubahan data proyeksi saat ini sedang ditutup.');
    }

    public function test_closed_projection_setting_prevents_mass_upload_projection(): void
    {
        Setting::set('rkap_projection_status', 'closed');

        Livewire::actingAs($this->verifikator)
            ->test(RkapProjectionUpload::class)
            ->set('periodId', $this->period->id)
            ->call('uploadAndImport')
            ->assertSet('errorsList', ['Penginputan dan perubahan data proyeksi saat ini sedang ditutup.']);
    }

    public function test_admin_can_update_submission_status_setting(): void
    {
        Livewire::actingAs($this->verifikator)
            ->test(RkapPeriodClosingManagement::class)
            ->set('closingDay', 15)
            ->set('projectionStatus', 'open')
            ->set('submissionStatus', 'closed')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Setting closing periode berhasil disimpan.');

        $this->assertEquals('closed', Setting::get('rkap_submission_status'));
    }

    public function test_closed_submission_setting_prevents_saving_draft(): void
    {
        Setting::set('rkap_submission_status', 'closed');

        $directorate = Directorate::create(['name' => 'Dir Test', 'code' => 'DIR']);
        $department = Department::create(['name' => 'Dept Test', 'code' => 'DEPT', 'directorate_id' => $directorate->id]);
        $bureau = Bureau::create(['name' => 'Bureau Test', 'code' => 'BUR', 'department_id' => $department->id]);

        $user = User::create([
            'name' => 'Bureau User',
            'email' => 'bureau_user@example.com',
            'password' => bcrypt('password'),
            'bureau_id' => $bureau->id,
        ]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Rkap\RkapSubmissionForm::class, ['periodId' => $this->period->id])
            ->call('saveDraft')
            ->assertSee('Pengisian Usulan RKAP Ditutup');
    }

    public function test_closed_submission_setting_prevents_submit_for_review(): void
    {
        Setting::set('rkap_submission_status', 'closed');

        $directorate = Directorate::create(['name' => 'Dir Test', 'code' => 'DIR']);
        $department = Department::create(['name' => 'Dept Test', 'code' => 'DEPT', 'directorate_id' => $directorate->id]);
        $bureau = Bureau::create(['name' => 'Bureau Test', 'code' => 'BUR', 'department_id' => $department->id]);

        $user = User::create([
            'name' => 'Bureau User',
            'email' => 'bureau_user2@example.com',
            'password' => bcrypt('password'),
            'bureau_id' => $bureau->id,
        ]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Rkap\RkapSubmissionForm::class, ['periodId' => $this->period->id])
            ->call('submitForReview')
            ->assertSee('Pengisian Usulan RKAP Ditutup');
    }
}
