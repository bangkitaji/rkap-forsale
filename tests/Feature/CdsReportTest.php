<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\CdsGroup;
use App\Models\CoaGroup;
use App\Models\Coa;
use App\Models\RkapPeriod;
use App\Models\RkapSubmission;
use App\Models\RkapWorkPlan;
use App\Models\WorkPlan;
use App\Models\RkapBudgetItem;
use App\Models\RkapBudgetItemCashOut;
use App\Models\Directorate;
use App\Models\Department;
use App\Models\Bureau;
use Livewire\Livewire;
use App\Livewire\Analytics\CdsReport;

class CdsReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected RkapPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->period = RkapPeriod::create([
            'year' => 2026,
            'title' => 'RKAP 2026',
            'status' => 'finalized',
            'submission_start' => now()->subDays(10),
            'submission_end' => now()->addDays(10),
        ]);
    }

    public function test_authenticated_user_can_access_cds_report_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('analytics-cds-report'));
        $response->assertStatus(200);
        $response->assertSee('Laporan CDS');
    }

    public function test_cds_report_calculates_cash_flow_data_correctly(): void
    {
        $directorate = Directorate::create(['name' => 'Dir Test', 'code' => 'DIR']);
        $department = Department::create(['name' => 'Dept Test', 'code' => 'DEPT', 'directorate_id' => $directorate->id]);
        $bureau = Bureau::create(['name' => 'Bureau Test', 'code' => 'BUR', 'department_id' => $department->id]);
        $wpMaster = WorkPlan::create(['title' => 'WP Master', 'code' => 'WPM']);

        $cdsGroup = CdsGroup::create(['code' => 'CDS01', 'name' => 'CDS Group 1']);
        $coaGroup = CoaGroup::create(['code' => 'CG01', 'name' => 'COA Group 1', 'cds_group_id' => $cdsGroup->id]);
        
        $coa = Coa::create([
            'code' => '511001',
            'title' => 'Beban Operasional Test',
            'coa_group_id' => $coaGroup->id,
            'is_active' => true,
        ]);

        $submission = RkapSubmission::create([
            'rkap_period_id' => $this->period->id,
            'bureau_id' => $bureau->id,
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);

        $rkapWp = RkapWorkPlan::create([
            'rkap_submission_id' => $submission->id,
            'work_plan_id' => $wpMaster->id,
            'program_code' => 'P01',
            'program_name' => 'Program 1',
        ]);

        $budgetItem = RkapBudgetItem::create([
            'rkap_work_plan_id' => $rkapWp->id,
            'account_code' => '511001',
            'description' => 'Test Item',
            'quantity' => 1,
            'unit_price' => 1000000,
            'total_price' => 1000000,
        ]);

        // Create cash out (cash flow schedule) for month 1 and month 2 (total 800.000)
        RkapBudgetItemCashOut::create([
            'rkap_budget_item_id' => $budgetItem->id,
            'month' => 1,
            'amount' => 300000,
        ]);
        RkapBudgetItemCashOut::create([
            'rkap_budget_item_id' => $budgetItem->id,
            'month' => 2,
            'amount' => 500000,
        ]);

        // Test YTD (all months) — Beban (account_code 511001) should be negative (-800,000)
        Livewire::actingAs($this->user)
            ->test(CdsReport::class)
            ->set('selectedPeriodId', $this->period->id)
            ->assertViewHas('grandTotalBudget', -800000.0);

        // Test month 1 filter — Beban (account_code 511001) for month 1 should be negative (-300,000)
        Livewire::actingAs($this->user)
            ->test(CdsReport::class)
            ->set('selectedPeriodId', $this->period->id)
            ->set('selectedMonth', 1)
            ->assertViewHas('grandTotalBudget', -300000.0);

        // Create revenue COA (411001) and item
        $coaRevenue = Coa::create([
            'code' => '411001',
            'title' => 'Pendapatan Usaha Test',
            'coa_group_id' => $coaGroup->id,
            'is_active' => true,
        ]);

        $budgetItemRevenue = RkapBudgetItem::create([
            'rkap_work_plan_id' => $rkapWp->id,
            'account_code' => '411001',
            'description' => 'Revenue Item',
            'quantity' => 1,
            'unit_price' => 1000000,
            'total_price' => 1000000,
            'flow_direction' => 'IN',
        ]);

        RkapBudgetItemCashOut::create([
            'rkap_budget_item_id' => $budgetItemRevenue->id,
            'month' => 1,
            'amount' => 1000000,
        ]);

        // YTD Grand Total Budget: -800,000 (Beban) + 1,000,000 (Pendapatan) = 200,000
        Livewire::actingAs($this->user)
            ->test(CdsReport::class)
            ->set('selectedPeriodId', $this->period->id)
            ->assertViewHas('grandTotalBudget', 200000.0);
    }

    public function test_cds001_cds002_cds003_are_treated_as_positive_revenue(): void
    {
        $directorate = Directorate::create(['name' => 'Dir Test 2', 'code' => 'DIR2']);
        $department = Department::create(['name' => 'Dept Test 2', 'code' => 'DEPT2', 'directorate_id' => $directorate->id]);
        $bureau = Bureau::create(['name' => 'Bureau Test 2', 'code' => 'BUR2', 'department_id' => $department->id]);
        $wpMaster = WorkPlan::create(['title' => 'WP Master 2', 'code' => 'WPM2']);

        // Create CDS001, CDS002, CDS003 (Revenue groups) and CDS004 (Expense group)
        $cds001 = CdsGroup::create(['code' => 'CDS001', 'name' => 'Cash received from farebox']);
        $cds004 = CdsGroup::create(['code' => 'CDS004', 'name' => 'Personel']);

        $cg01 = CoaGroup::create(['code' => 'CG_REV', 'name' => 'COA Rev Group', 'cds_group_id' => $cds001->id]);
        $cg04 = CoaGroup::create(['code' => 'CG_EXP', 'name' => 'COA Exp Group', 'cds_group_id' => $cds004->id]);

        $coaRev = Coa::create(['code' => '400001', 'title' => 'Farebox Revenue', 'coa_group_id' => $cg01->id, 'is_active' => true]);
        $coaExp = Coa::create(['code' => '500001', 'title' => 'Gaji Staff', 'coa_group_id' => $cg04->id, 'is_active' => true]);

        $submission = RkapSubmission::create([
            'rkap_period_id' => $this->period->id,
            'bureau_id' => $bureau->id,
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);

        $rkapWp = RkapWorkPlan::create([
            'rkap_submission_id' => $submission->id,
            'work_plan_id' => $wpMaster->id,
            'program_code' => 'P02',
            'program_name' => 'Program 2',
        ]);

        $itemRev = RkapBudgetItem::create([
            'rkap_work_plan_id' => $rkapWp->id,
            'account_code' => '400001',
            'description' => 'Farebox item',
            'quantity' => 1,
            'unit_price' => 5000000,
            'total_price' => 5000000,
        ]);

        $itemExp = RkapBudgetItem::create([
            'rkap_work_plan_id' => $rkapWp->id,
            'account_code' => '500001',
            'description' => 'Gaji item',
            'quantity' => 1,
            'unit_price' => 2000000,
            'total_price' => 2000000,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(CdsReport::class)
            ->set('selectedPeriodId', $this->period->id);

        $reportData = $component->viewData('reportData');

        $cds001Data = collect($reportData)->first(fn($i) => $i['group']->code === 'CDS001');
        $cds004Data = collect($reportData)->first(fn($i) => $i['group']->code === 'CDS004');

        $this->assertNotNull($cds001Data);
        $this->assertNotNull($cds004Data);
        $this->assertEquals(5000000.0, $cds001Data['budget']);
        $this->assertEquals(-2000000.0, $cds004Data['budget']);
    }
}
