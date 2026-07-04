<?php

namespace Tests\Feature;

use Tests\TestCase;
use Livewire\Livewire;
use App\Livewire\Rkap\RkapRealizationUpload;
use App\Models\RkapPeriod;
use App\Models\RkapBudgetItem;
use App\Models\RkapBudgetItemRealization;
use App\Models\RkapSubmission;
use App\Models\Bureau;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\User;
use App\Models\WorkPlan;
use App\Models\RkapWorkPlan;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RkapRealizationUploadTest extends TestCase
{
    use RefreshDatabase;

    protected User $verifikator;
    protected RkapPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        // Create role and permission
        $roleVerifikator = Role::firstOrCreate(['name' => 'verifikator']);
        $permRealizationUpload = Permission::firstOrCreate(['name' => 'rkap.realization.upload', 'guard_name' => 'web']);
        $roleVerifikator->givePermissionTo($permRealizationUpload);

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

    public function test_unauthorized_user_cannot_access(): void
    {
        $user = User::create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user);

        Livewire::test(RkapRealizationUpload::class)
            ->assertStatus(403);
    }

    public function test_authorized_user_can_access_and_flow_works(): void
    {
        $this->actingAs($this->verifikator);

        // Set up required relations for RkapBudgetItem (Approved submission is required for period option filtering)
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
            'description' => 'Item 1',
            'quantity' => 1,
            'unit_price' => 10000,
        ]);

        // 1. Initial render (no period selected)
        Livewire::test(RkapRealizationUpload::class)
            ->assertStatus(200)
            ->assertDontSee('Bulan Realisasi')
            // Select period
            ->set('periodId', $this->period->id)
            ->assertSee('Bulan Realisasi')
            ->assertDontSee('Download Template');

        // 2. Select period and check month options
        // Create an existing realization for month 3 (Maret)
        RkapBudgetItemRealization::create([
            'rkap_budget_item_id' => $budgetItem->id,
            'rkap_period_id' => $this->period->id,
            'month' => 3, // Maret
            'amount' => 5000,
            'uploaded_by' => $this->verifikator->id,
            'uploaded_at' => now(),
        ]);

        // Test that month options only shows months without realization (excludes 3)
        $component = Livewire::test(RkapRealizationUpload::class)
            ->set('periodId', $this->period->id);

        $monthOptions = $component->get('monthOptions');
        $this->assertArrayNotHasKey(3, $monthOptions); // Maret is excluded
        $this->assertArrayHasKey(1, $monthOptions); // Januari is included
        $this->assertArrayHasKey(6, $monthOptions); // Juni is included

        // Select month 6
        $component->set('month', 6)
            ->assertSee('Download Template');
    }

    public function test_realization_template_export_generates_correct_data(): void
    {
        // Setup submission and budget item
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
            'description' => 'Item 1',
            'quantity' => 1,
            'unit_price' => 10000,
        ]);

        // Export for month 8 (Agustus)
        $export = new \App\Exports\RkapRealizationTemplateExport($this->period->id, 8);
        $rows = $export->array();

        // Row 0 is headers
        $this->assertEquals('budget_item_id', $rows[0][0]);
        // Row 1 is hints
        $this->assertEquals('(integer)', $rows[1][0]);
        
        // Row 2 is data
        $dataRow = $rows[2];
        $this->assertEquals($budgetItem->id, $dataRow[0]);
        $this->assertEquals(8, $dataRow[12]); // month should be 8
        $this->assertEquals(0, $dataRow[13]); // amount should be 0
    }

    public function test_template_download_restricted_to_current_year_finalized_period(): void
    {
        $this->actingAs($this->verifikator);

        // 1. Past period
        $pastYear = (int)date('Y') - 1;
        $pastPeriod = RkapPeriod::create([
            'year' => $pastYear,
            'title' => 'RKAP ' . $pastYear,
            'status' => 'finalized',
            'submission_start' => now()->subYear(),
            'submission_end' => now()->subYear()->addMonth(),
        ]);

        // Try downloading Excel template for past period
        $responseExcel = $this->get(route('rkap-realization-template-download', [
            'period_id' => $pastPeriod->id,
            'month' => 1
        ]));
        $responseExcel->assertStatus(403);

        // Try downloading CSV template for past period
        $responseCsv = $this->get(route('rkap-realization-template-download-csv', [
            'period_id' => $pastPeriod->id,
            'month' => 1
        ]));
        $responseCsv->assertStatus(403);

        // Try downloading Excel template for current finalized period (should succeed)
        $responseCurrentExcel = $this->get(route('rkap-realization-template-download', [
            'period_id' => $this->period->id,
            'month' => 1
        ]));
        $responseCurrentExcel->assertStatus(200);
    }

    public function test_realization_list_is_rendered_with_correct_filters_and_search(): void
    {
        $this->actingAs($this->verifikator);

        // Setup submission, budget item, and realization
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
            'month' => 4, // April
            'amount' => 7500,
            'uploaded_by' => $this->verifikator->id,
            'uploaded_at' => now(),
        ]);

        // 1. Without period, should see the filter prompt
        Livewire::test(RkapRealizationUpload::class)
            ->assertSee('Silakan pilih Periode RKAP di atas')
            ->assertDontSee('Target Item Description');

        // 2. With period, should see the realization details
        Livewire::test(RkapRealizationUpload::class)
            ->set('periodId', $this->period->id)
            ->assertSee('Target Item Description')
            ->assertSee('521111')
            ->assertSee('B1')
            ->assertSee('April')
            ->assertSee('Rp 7.500');

        // 3. Search matching COA
        Livewire::test(RkapRealizationUpload::class)
            ->set('periodId', $this->period->id)
            ->set('search', '521111')
            ->assertSee('Target Item Description');

        // 4. Search non-matching COA
        Livewire::test(RkapRealizationUpload::class)
            ->set('periodId', $this->period->id)
            ->set('search', '999999')
            ->assertDontSee('Target Item Description');

        // 5. Filter by matching month
        Livewire::test(RkapRealizationUpload::class)
            ->set('periodId', $this->period->id)
            ->set('filterMonth', 4)
            ->assertSee('Target Item Description')
            ->assertSee('April');

        // 6. Filter by non-matching month
        Livewire::test(RkapRealizationUpload::class)
            ->set('periodId', $this->period->id)
            ->set('filterMonth', 5)
            ->assertDontSee('Target Item Description');
    }

    public function test_realization_can_be_deleted(): void
    {
        $this->actingAs($this->verifikator);

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
            'month' => 4, // April
            'amount' => 7500,
            'uploaded_by' => $this->verifikator->id,
            'uploaded_at' => now(),
        ]);

        // Delete realization
        Livewire::test(RkapRealizationUpload::class)
            ->set('periodId', $this->period->id)
            ->call('deleteRealization', $realization->id)
            ->assertHasNoErrors()
            ->assertStatus(200);

        // Assert deleted from database
        $this->assertDatabaseMissing('rkap_budget_item_realizations', [
            'id' => $realization->id,
        ]);
    }

    public function test_cannot_upload_realization_for_past_finalized_period(): void
    {
        $this->actingAs($this->verifikator);

        // 1. Create a finalized period for a past year
        $pastYear = (int)date('Y') - 1;
        $pastPeriod = RkapPeriod::create([
            'year' => $pastYear,
            'title' => 'RKAP ' . $pastYear,
            'status' => 'finalized',
            'submission_start' => now()->subYear(),
            'submission_end' => now()->subYear()->addMonth(),
        ]);

        // 2. Setup approved submission and items for this past period
        $directorate = Directorate::create(['code' => 'D_PAST', 'name' => 'Dir Past']);
        $department = Department::create(['directorate_id' => $directorate->id, 'code' => 'DP_PAST', 'name' => 'Dept Past']);
        $bureau = Bureau::create(['department_id' => $department->id, 'code' => 'B_PAST', 'name' => 'Bur Past']);
        
        $submission = RkapSubmission::create([
            'rkap_period_id' => $pastPeriod->id,
            'bureau_id' => $bureau->id,
            'created_by' => $this->verifikator->id,
            'status' => 'approved',
            'total_budget' => 50000,
        ]);
        
        $wpMaster = WorkPlan::create([
            'code' => 'WP_PAST',
            'title' => 'Work Plan Past',
        ]);

        $workPlan = RkapWorkPlan::create([
            'rkap_submission_id' => $submission->id,
            'work_plan_id' => $wpMaster->id,
            'program_code' => 'WP_PAST',
            'program_name' => 'Work Plan Past',
        ]);

        $budgetItem = RkapBudgetItem::create([
            'rkap_work_plan_id' => $workPlan->id,
            'account_code' => '521111',
            'description' => 'Past Item Description',
            'quantity' => 1,
            'unit_price' => 50000,
        ]);

        // 3. Test that this past period is NOT in the period options
        $component = Livewire::test(RkapRealizationUpload::class);
        $periodOptions = $component->get('periodOptions');
        
        $this->assertFalse($periodOptions->contains('id', $pastPeriod->id));

        // 4. Test upload fails validation for this past period
        $file = \Illuminate\Http\UploadedFile::fake()->create('realisasi.xlsx', 100);
        $component->set('periodId', $pastPeriod->id)
            ->set('month', 1)
            ->set('file', $file)
            ->call('uploadAndImport');

        $errors = $component->get('errorsList');
        $this->assertContains(
            'Realisasi hanya dapat diunggah untuk periode RKAP tahun berjalan (' . date('Y') . ') dengan status Finalized.',
            $errors
        );
    }

    public function test_realization_list_shows_department_accumulation_and_filters(): void
    {
        $this->actingAs($this->verifikator);

        // Setup submission and budget items
        $directorate = Directorate::create(['code' => 'D_ACC', 'name' => 'Dir Acc']);
        $department = Department::create(['directorate_id' => $directorate->id, 'code' => 'DP_ACC', 'name' => 'Dept Acc']);
        $bureau = Bureau::create(['department_id' => $department->id, 'code' => 'B_ACC', 'name' => 'Bur Acc']);
        
        $submission = RkapSubmission::create([
            'rkap_period_id' => $this->period->id,
            'bureau_id' => $bureau->id,
            'created_by' => $this->verifikator->id,
            'status' => 'approved',
            'total_budget' => 100000,
        ]);
        
        $wpMaster = WorkPlan::create([
            'code' => 'WP_ACC',
            'title' => 'Work Plan Acc',
        ]);

        $workPlan = RkapWorkPlan::create([
            'rkap_submission_id' => $submission->id,
            'work_plan_id' => $wpMaster->id,
            'program_code' => 'WP_ACC',
            'program_name' => 'Work Plan Acc',
        ]);

        $bi1 = RkapBudgetItem::create([
            'rkap_work_plan_id' => $workPlan->id,
            'account_code' => '521111',
            'description' => 'Item 1',
            'quantity' => 1,
            'unit_price' => 10000,
        ]);

        $bi2 = RkapBudgetItem::create([
            'rkap_work_plan_id' => $workPlan->id,
            'account_code' => '521112',
            'description' => 'Item 2',
            'quantity' => 1,
            'unit_price' => 20000,
        ]);

        // Create realizations for Bureau B_ACC under Department DP_ACC (total 5000 + 7500 = 12500)
        RkapBudgetItemRealization::create([
            'rkap_budget_item_id' => $bi1->id,
            'rkap_period_id' => $this->period->id,
            'month' => 4,
            'amount' => 5000,
            'uploaded_by' => $this->verifikator->id,
            'uploaded_at' => now(),
        ]);

        RkapBudgetItemRealization::create([
            'rkap_budget_item_id' => $bi2->id,
            'rkap_period_id' => $this->period->id,
            'month' => 4,
            'amount' => 7500,
            'uploaded_by' => $this->verifikator->id,
            'uploaded_at' => now(),
        ]);

        // Test that Livewire component has the accumulations and renders them
        $component = Livewire::test(RkapRealizationUpload::class)
            ->set('periodId', $this->period->id);

        $accumulations = $component->get('departmentAccumulations');
        $this->assertNotEmpty($accumulations);
        $this->assertEquals('DP_ACC', $accumulations->first()->code);
        $this->assertEquals(12500.0, $accumulations->first()->total_amount);

        $component->assertSee('Ringkasan Akumulasi Realisasi per Departemen')
            ->assertSee('DP_ACC')
            ->assertSee('Rp 12.500');

        // Test filter by department triggers and filters the detail list
        $component->set('filterDepartmentId', $department->id)
            ->assertSee('Departemen: ' . $department->name);
    }
}
