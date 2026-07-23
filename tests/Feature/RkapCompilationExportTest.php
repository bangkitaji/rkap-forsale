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
use App\Models\CashflowGroup;
use App\Models\DifferenceGroup;
use App\Models\Coa;
use App\Exports\RkapCompilationExport;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RkapCompilationExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_compilation_includes_cashflow_group_and_difference_group(): void
    {
        $dir = Directorate::create(['code' => 'DIR_TEST', 'name' => 'Test Directorate']);
        $dept = Department::create(['code' => 'DEPT_TEST', 'name' => 'Test Department', 'directorate_id' => $dir->id]);
        $bureau = Bureau::create(['code' => 'BUR_TEST', 'name' => 'Test Bureau', 'department_id' => $dept->id]);
        $user = User::create(['name' => 'Test User', 'email' => 'test@example.com', 'password' => bcrypt('password')]);

        $period = RkapPeriod::create(['year' => 2026, 'title' => 'RKAP 2026', 'status' => 'finalized']);

        $cfGroup = CashflowGroup::create(['code' => 'CF01', 'name' => 'Operational Inflow']);
        $diffGroup = DifferenceGroup::create(['code' => 'DG01', 'name' => 'Asset Difference']);

        $coa = Coa::create([
            'code' => '510001',
            'title' => 'Beban Gaji',
            'cashflow_group_id' => $cfGroup->id,
        ]);

        $sub = RkapSubmission::create([
            'rkap_period_id' => $period->id,
            'bureau_id' => $bureau->id,
            'created_by' => $user->id,
            'status' => 'approved',
            'total_budget' => 100000,
        ]);

        $wp = RkapWorkPlan::create([
            'rkap_submission_id' => $sub->id,
            'program_code' => 'PROG01',
            'program_name' => 'Program Test',
        ]);

        $bi = RkapBudgetItem::create([
            'rkap_work_plan_id' => $wp->id,
            'account_code' => '510001',
            'description' => 'Gaji Karyawan',
            'unit' => 'Bulan',
            'quantity' => 12,
            'unit_price' => 10000,
            'total_price' => 120000,
            'difference_group_id' => $diffGroup->id,
        ]);

        $export = new RkapCompilationExport(collect([$sub]), 'RKAP 2026');
        $data = $export->array();

        // Check header row 1 contains Kode Direktorat, Kode Departemen, Kode Biro
        $this->assertEquals('Kode Direktorat', $data[0][0]);
        $this->assertEquals('Kode Departemen', $data[0][1]);
        $this->assertEquals('Kode Biro', $data[0][2]);

        // Check header row 1 contains separated Cashflow and Difference headers
        $this->assertContains('Kode Group Cashflow', $data[0]);
        $this->assertContains('Nama Group Cashflow', $data[0]);
        $this->assertContains('Kode Group Difference', $data[0]);
        $this->assertContains('Nama Group Difference', $data[0]);

        $cfCodeIndex = array_search('Kode Group Cashflow', $data[0]);
        $cfNameIndex = array_search('Nama Group Cashflow', $data[0]);
        $diffCodeIndex = array_search('Kode Group Difference', $data[0]);
        $diffNameIndex = array_search('Nama Group Difference', $data[0]);

        $this->assertEquals(5, $cfCodeIndex);
        $this->assertEquals(6, $cfNameIndex);
        $this->assertEquals(7, $diffCodeIndex);
        $this->assertEquals(8, $diffNameIndex);

        // Check data row contains codes for unit organization and separated groups
        $dataRow = $data[2];
        $this->assertEquals('DIR_TEST', $dataRow[0]);
        $this->assertEquals('DEPT_TEST', $dataRow[1]);
        $this->assertEquals('BUR_TEST', $dataRow[2]);
        $this->assertEquals('CF01', $dataRow[$cfCodeIndex]);
        $this->assertEquals('Operational Inflow', $dataRow[$cfNameIndex]);
        $this->assertEquals('DG01', $dataRow[$diffCodeIndex]);
        $this->assertEquals('Asset Difference', $dataRow[$diffNameIndex]);
    }
}
