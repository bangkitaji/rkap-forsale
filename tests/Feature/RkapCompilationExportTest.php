<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\RkapPeriod;
use App\Models\Bureau;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\RkapSubmission;
use App\Models\RkapWorkPlan;
use App\Models\RkapBudgetItem;
use App\Models\RkapBudgetItemMonthly;
use App\Models\RkapBudgetItemCashOut;
use App\Models\CashflowGroup;
use App\Models\DifferenceGroup;
use App\Models\Coa;
use App\Models\CoaGroup;
use App\Exports\RkapCompilationExport;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RkapCompilationExportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * New column layout (0-indexed):
     * 0  Dir
     * 1  Dept
     * 2  Biro
     * 3  Kode Program Kerja
     * 4  Nama Program Kerja
     * 5  Kode Kegiatan
     * 6  Nama Kegiatan
     * 7  Kode Anggaran  (coa_group.code)
     * 8  Nama Anggaran  (coa_group.name)
     * 9  COA SAP        (coa.code)
     * 10 COA SAP Desc   (coa.title)
     * 11 Kode CF        (cashflow_group.code)
     * 12 Nama CF        (cashflow_group.name)
     * 13 Kode Diff      (difference_group.code)
     * 14 Nama Group Diff (difference_group.name)
     * 15-26 Budget Jan-Des
     * 27    Budget Total
     * 28-39 CF Jan-Des
     * 40    CF Total
     * 41-52 Diff Jan-Des
     * 53    Diff Total
     */
    public function test_export_compilation_has_correct_headers(): void
    {
        $dir    = Directorate::create(['code' => 'DIR_TEST', 'name' => 'Test Directorate']);
        $dept   = Department::create(['code' => 'DEPT_TEST', 'name' => 'Test Department', 'directorate_id' => $dir->id]);
        $bureau = Bureau::create(['code' => 'BUR_TEST', 'name' => 'Test Bureau', 'department_id' => $dept->id]);
        $user   = User::create(['name' => 'Test User', 'email' => 'test@example.com', 'password' => bcrypt('password')]);
        $period = RkapPeriod::create(['year' => 2026, 'title' => 'RKAP 2026', 'status' => 'finalized']);

        $cfGroup   = CashflowGroup::create(['code' => 'CF01', 'name' => 'Operational Inflow']);
        $diffGroup = DifferenceGroup::create(['code' => 'DG01', 'name' => 'Asset Difference']);
        $coaGroup  = CoaGroup::create(['code' => 'CG01', 'name' => 'Beban Operasional']);

        $coa = Coa::create([
            'code'             => '510001',
            'title'            => 'Beban Gaji',
            'cashflow_group_id' => $cfGroup->id,
            'coa_group_id'     => $coaGroup->id,
        ]);

        $sub = RkapSubmission::create([
            'rkap_period_id' => $period->id,
            'bureau_id'      => $bureau->id,
            'created_by'     => $user->id,
            'status'         => 'approved',
            'total_budget'   => 100000,
        ]);

        $wp = RkapWorkPlan::create([
            'rkap_submission_id' => $sub->id,
            'program_code'       => 'PROG01',
            'program_name'       => 'Program Test',
        ]);

        RkapBudgetItem::create([
            'rkap_work_plan_id'   => $wp->id,
            'account_code'        => '510001',
            'description'         => 'Gaji Karyawan',
            'difference_group_id' => $diffGroup->id,
        ]);

        $export = new RkapCompilationExport(collect([$sub]), 'RKAP 2026');
        $data   = $export->array();

        // Row 1 static headers
        $this->assertEquals('Dir',              $data[0][0]);
        $this->assertEquals('Dept',             $data[0][1]);
        $this->assertEquals('Biro',             $data[0][2]);
        $this->assertEquals('Kode Program Kerja', $data[0][3]);
        $this->assertEquals('Nama Program Kerja', $data[0][4]);
        $this->assertEquals('Kode Kegiatan',    $data[0][5]);
        $this->assertEquals('Nama Kegiatan',    $data[0][6]);
        $this->assertEquals('Kode Anggaran',    $data[0][7]);
        $this->assertEquals('Nama Anggaran',    $data[0][8]);
        $this->assertEquals('COA SAP',          $data[0][9]);
        $this->assertEquals('COA SAP Desc',     $data[0][10]);
        $this->assertEquals('Kode CF',          $data[0][11]);
        $this->assertEquals('Nama CF',          $data[0][12]);
        $this->assertEquals('Kode Diff',        $data[0][13]);
        $this->assertEquals('Nama Group Diff',  $data[0][14]);

        // Row 1 group headers start at col 15
        $this->assertEquals('Budget', $data[0][15]);
        $this->assertEquals('CF',     $data[0][28]);
        $this->assertEquals('Diff',   $data[0][41]);

        // Row 2 static headers are empty
        $this->assertEquals('', $data[1][0]);
        $this->assertEquals('', $data[1][2]);

        // Row 2 month sub-headers
        $this->assertEquals('Jan',   $data[1][15]);
        $this->assertEquals('Des',   $data[1][26]);
        $this->assertEquals('Total', $data[1][27]);
        $this->assertEquals('Jan',   $data[1][28]);
        $this->assertEquals('Total', $data[1][40]);
        $this->assertEquals('Jan',   $data[1][41]);
        $this->assertEquals('Total', $data[1][53]);

        // Data row
        $dataRow = $data[2];
        $this->assertEquals('DIR_TEST',  $dataRow[0]);
        $this->assertEquals('DEPT_TEST', $dataRow[1]);
        $this->assertEquals('BUR_TEST',  $dataRow[2]);
        $this->assertEquals('CF01',      $dataRow[11]);
        $this->assertEquals('Operational Inflow', $dataRow[12]);
        $this->assertEquals('DG01',      $dataRow[13]);
        $this->assertEquals('Asset Difference',   $dataRow[14]);
    }

    public function test_export_compilation_includes_coa_group(): void
    {
        $dir    = Directorate::create(['code' => 'DIR_TEST', 'name' => 'Test Directorate']);
        $dept   = Department::create(['code' => 'DEPT_TEST', 'name' => 'Test Department', 'directorate_id' => $dir->id]);
        $bureau = Bureau::create(['code' => 'BUR_TEST', 'name' => 'Test Bureau', 'department_id' => $dept->id]);
        $user   = User::create(['name' => 'Test User', 'email' => 'test2@example.com', 'password' => bcrypt('password')]);
        $period = RkapPeriod::create(['year' => 2026, 'title' => 'RKAP 2026', 'status' => 'finalized']);

        $coaGroup = CoaGroup::create(['code' => 'CG01', 'name' => 'Beban Operasional']);
        $coa      = Coa::create(['code' => '510001', 'title' => 'Beban Gaji', 'coa_group_id' => $coaGroup->id]);

        $sub = RkapSubmission::create([
            'rkap_period_id' => $period->id,
            'bureau_id'      => $bureau->id,
            'created_by'     => $user->id,
            'status'         => 'approved',
            'total_budget'   => 100000,
        ]);

        $wp = RkapWorkPlan::create(['rkap_submission_id' => $sub->id, 'program_code' => 'P1', 'program_name' => 'P1']);

        RkapBudgetItem::create([
            'rkap_work_plan_id' => $wp->id,
            'account_code'      => '510001',
            'description'       => 'Gaji',
        ]);

        $export = new RkapCompilationExport(collect([$sub]), 'RKAP 2026');
        $data   = $export->array();

        $dataRow = $data[2];
        $this->assertEquals('CG01',              $dataRow[7]);
        $this->assertEquals('Beban Operasional', $dataRow[8]);
        $this->assertEquals('510001',            $dataRow[9]);
        $this->assertEquals('Beban Gaji',        $dataRow[10]);
    }

    public function test_export_compilation_groups_by_activity_code(): void
    {
        $dir    = Directorate::create(['code' => 'DIR_TEST', 'name' => 'Test Directorate']);
        $dept   = Department::create(['code' => 'DEPT_TEST', 'name' => 'Test Department', 'directorate_id' => $dir->id]);
        $bureau = Bureau::create(['code' => 'BUR_TEST', 'name' => 'Test Bureau', 'department_id' => $dept->id]);
        $user   = User::create(['name' => 'Test User', 'email' => 'test3@example.com', 'password' => bcrypt('password')]);
        $period = RkapPeriod::create(['year' => 2026, 'title' => 'RKAP 2026', 'status' => 'finalized']);

        $workPlanModel1 = \App\Models\WorkPlan::create(['code' => 'WP01', 'title' => 'Work Plan 1', 'approval_status' => 'approved']);
        $workPlanModel2 = \App\Models\WorkPlan::create(['code' => 'WP02', 'title' => 'Work Plan 2', 'approval_status' => 'approved']);

        $actModelB = \App\Models\Activity::create(['work_plan_id' => $workPlanModel1->id, 'code' => 'ACT_B', 'title' => 'Kegiatan B', 'approval_status' => 'approved']);
        $actModelA = \App\Models\Activity::create(['work_plan_id' => $workPlanModel2->id, 'code' => 'ACT_A', 'title' => 'Kegiatan A', 'approval_status' => 'approved']);

        $sub = RkapSubmission::create([
            'rkap_period_id' => $period->id,
            'bureau_id'      => $bureau->id,
            'created_by'     => $user->id,
            'status'         => 'approved',
            'total_budget'   => 200000,
        ]);

        // WP 1 has Activity ACT_B (created first)
        $wp1 = RkapWorkPlan::create([
            'rkap_submission_id' => $sub->id,
            'work_plan_id'       => $workPlanModel1->id,
            'activity_id'        => $actModelB->id,
            'program_code'       => 'ACT_B',
            'program_name'       => 'Kegiatan B',
        ]);
        RkapBudgetItem::create([
            'rkap_work_plan_id' => $wp1->id,
            'account_code'      => '510001',
            'description'       => 'Item B',
            'quantity'          => 1,
            'unit_price'        => 100000,
            'total_price'       => 100000,
        ]);

        // WP 2 has Activity ACT_A (created second)
        $wp2 = RkapWorkPlan::create([
            'rkap_submission_id' => $sub->id,
            'work_plan_id'       => $workPlanModel2->id,
            'activity_id'        => $actModelA->id,
            'program_code'       => 'ACT_A',
            'program_name'       => 'Kegiatan A',
        ]);
        RkapBudgetItem::create([
            'rkap_work_plan_id' => $wp2->id,
            'account_code'      => '510002',
            'description'       => 'Item A',
            'quantity'          => 1,
            'unit_price'        => 100000,
            'total_price'       => 100000,
        ]);

        $export = new RkapCompilationExport(collect([$sub]), 'RKAP 2026');
        $data   = $export->array();

        // Row 2 is data start; Kode Kegiatan is at index 5
        $this->assertEquals('ACT_A', $data[2][5]);
        $this->assertEquals('ACT_B', $data[3][5]);
    }

    public function test_export_compilation_diff_equals_budget_minus_cf(): void
    {
        $dir    = Directorate::create(['code' => 'DIR_TEST', 'name' => 'Test Directorate']);
        $dept   = Department::create(['code' => 'DEPT_TEST', 'name' => 'Test Department', 'directorate_id' => $dir->id]);
        $bureau = Bureau::create(['code' => 'BUR_TEST', 'name' => 'Test Bureau', 'department_id' => $dept->id]);
        $user   = User::create(['name' => 'Test User', 'email' => 'test4@example.com', 'password' => bcrypt('password')]);
        $period = RkapPeriod::create(['year' => 2026, 'title' => 'RKAP 2026', 'status' => 'finalized']);

        $sub = RkapSubmission::create([
            'rkap_period_id' => $period->id,
            'bureau_id'      => $bureau->id,
            'created_by'     => $user->id,
            'status'         => 'approved',
            'total_budget'   => 500000,
        ]);

        $wp = RkapWorkPlan::create(['rkap_submission_id' => $sub->id, 'program_code' => 'P1', 'program_name' => 'P1']);
        $bi = RkapBudgetItem::create([
            'rkap_work_plan_id' => $wp->id,
            'account_code'      => '510001',
            'description'       => 'Test Item',
        ]);

        // Budget: Jan=100000, Feb=200000
        RkapBudgetItemMonthly::create(['rkap_budget_item_id' => $bi->id, 'month' => 1, 'amount' => 100000]);
        RkapBudgetItemMonthly::create(['rkap_budget_item_id' => $bi->id, 'month' => 2, 'amount' => 200000]);

        // CF: Jan=60000, Feb=150000
        RkapBudgetItemCashOut::create(['rkap_budget_item_id' => $bi->id, 'month' => 1, 'amount' => 60000]);
        RkapBudgetItemCashOut::create(['rkap_budget_item_id' => $bi->id, 'month' => 2, 'amount' => 150000]);

        $export = new RkapCompilationExport(collect([$sub]), 'RKAP 2026');
        $data   = $export->array();

        // Row 2 is first data row
        $dataRow = $data[2];

        // Budget cols: 15=Jan, 16=Feb, 27=Total
        $this->assertEquals(100000, $dataRow[15]); // Budget Jan
        $this->assertEquals(200000, $dataRow[16]); // Budget Feb
        $this->assertEquals(300000, $dataRow[27]); // Budget Total

        // CF cols: 28=Jan, 29=Feb, 40=Total
        $this->assertEquals(60000,  $dataRow[28]); // CF Jan
        $this->assertEquals(150000, $dataRow[29]); // CF Feb
        $this->assertEquals(210000, $dataRow[40]); // CF Total

        // Diff cols: 41=Jan, 42=Feb, 53=Total  (Budget - CF)
        $this->assertEquals(40000,  $dataRow[41]); // Diff Jan  = 100000-60000
        $this->assertEquals(50000,  $dataRow[42]); // Diff Feb  = 200000-150000
        $this->assertEquals(90000,  $dataRow[53]); // Diff Total = 300000-210000
    }
}

