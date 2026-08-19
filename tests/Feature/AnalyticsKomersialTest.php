<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\RkapPeriod;
use App\Models\Bureau;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\ReportGroup;
use App\Models\CoaGroup;
use App\Models\Coa;
use App\Models\RkapSubmission;
use App\Models\RkapWorkPlan;
use App\Models\RkapBudgetItem;
use App\Models\RkapBudgetItemMonthly;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AnalyticsKomersialTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected RkapPeriod $period;
    protected CoaGroup $cgFarebox;
    protected CoaGroup $cgNonFarebox;

    protected function setUp(): void
    {
        parent::setUp();

        $perm = Permission::firstOrCreate(['name' => 'rkap.dashboard.view', 'guard_name' => 'web']);
        $roleAdmin = Role::firstOrCreate(['name' => 'admin']);
        $roleAdmin->givePermissionTo($perm);

        $directorate = Directorate::create(['code' => 'DIR01', 'name' => 'Directorate Test', 'is_active' => true]);
        $department = Department::create(['directorate_id' => $directorate->id, 'code' => 'DEP01', 'name' => 'Dept Test', 'is_active' => true]);
        $bureau = Bureau::create(['department_id' => $department->id, 'code' => 'BUR01', 'name' => 'Bureau Test', 'is_active' => true]);

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin_komersial@example.com',
            'password' => bcrypt('password'),
            'bureau_id' => $bureau->id,
            'department_id' => $department->id,
            'directorate_id' => $directorate->id,
        ]);
        $this->admin->assignRole('admin');

        $this->period = RkapPeriod::create([
            'year' => (int) date('Y'),
            'title' => 'RKAP ' . date('Y'),
            'status' => 'finalized',
        ]);

        $rgDirectCost = ReportGroup::create([
            'code' => 'PL0002',
            'name' => 'Direct Cost',
            'type' => 'PL',
        ]);

        $this->cgFarebox = CoaGroup::create([
            'code' => '5007',
            'name' => 'Kom Farebox',
            'report_group_id' => $rgDirectCost->id,
        ]);

        $this->cgNonFarebox = CoaGroup::create([
            'code' => '5008',
            'name' => 'Kom Non Farebox',
            'report_group_id' => $rgDirectCost->id,
        ]);

        $coa1 = Coa::create([
            'code' => '5501000001',
            'title' => 'Beban Tiket Farebox',
            'coa_group_id' => $this->cgFarebox->id,
        ]);

        $coa2 = Coa::create([
            'code' => '5501000003',
            'title' => 'Beban Retail Non Farebox',
            'coa_group_id' => $this->cgNonFarebox->id,
        ]);

        $submission = RkapSubmission::create([
            'rkap_period_id' => $this->period->id,
            'bureau_id' => $bureau->id,
            'created_by' => $this->admin->id,
            'status' => 'approved',
            'total_budget' => 3000000,
        ]);

        $wp = RkapWorkPlan::create([
            'rkap_submission_id' => $submission->id,
            'program_name' => 'Program Operasional',
        ]);

        $item1 = RkapBudgetItem::create([
            'rkap_work_plan_id' => $wp->id,
            'account_code' => $coa1->code,
            'description' => 'Operasional Tiket',
            'quantity' => 1,
            'unit' => 'Paket',
            'unit_price' => 1000000,
            'total_price' => 1000000,
            'projection' => 1000000,
        ]);

        $item2 = RkapBudgetItem::create([
            'rkap_work_plan_id' => $wp->id,
            'account_code' => $coa2->code,
            'description' => 'Operasional Non Tiket',
            'quantity' => 1,
            'unit' => 'Paket',
            'unit_price' => 2000000,
            'total_price' => 2000000,
            'projection' => 2000000,
        ]);

        RkapBudgetItemMonthly::create([
            'rkap_budget_item_id' => $item1->id,
            'month' => 1,
            'amount' => 1000000,
        ]);

        RkapBudgetItemMonthly::create([
            'rkap_budget_item_id' => $item2->id,
            'month' => 1,
            'amount' => 2000000,
        ]);
    }

    public function test_analytics_report_renders_komersial_instead_of_separate_farebox_items(): void
    {
        $response = $this->actingAs($this->admin)->get(route('analytics-report', ['period_id' => $this->period->id]));

        $response->assertStatus(200);
        $response->assertSee('Komersial');
        $response->assertDontSee('Kom Farebox');
        $response->assertDontSee('Kom Non Farebox');
    }

    public function test_coa_group_detail_returns_combined_data_for_multiple_coa_group_ids(): void
    {
        $combinedIds = $this->cgFarebox->id . ',' . $this->cgNonFarebox->id;

        $response = $this->actingAs($this->admin)->getJson("/analytics/coa-group-detail?coa_group_id={$combinedIds}&period_id={$this->period->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertEquals('5501000001', $data[0]['coa_code']);
        $this->assertEquals('5501000003', $data[1]['coa_code']);
    }
}
