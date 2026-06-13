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
            'year' => 2026,
            'title' => 'RKAP 2026',
            'status' => 'open',
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
        // Set up required relations for RkapBudgetItem
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
}
