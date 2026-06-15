<?php

namespace Tests\Feature;

use Tests\TestCase;
use Livewire\Livewire;
use App\Livewire\Settings\DataMigrationUpload;
use App\Models\RkapPeriod;
use App\Models\Bureau;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\User;
use App\Models\WorkPlan;
use App\Models\Coa;
use App\Models\RkapSubmission;
use App\Models\RkapWorkPlan;
use App\Models\RkapBudgetItem;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

class DataMigrationUploadTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected RkapPeriod $period;
    protected Bureau $bureau;
    protected WorkPlan $workPlanMaster;
    protected Coa $coaMaster;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['name' => 'admin']);

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->admin->assignRole($roleAdmin);

        $this->period = RkapPeriod::create([
            'year' => 2026,
            'title' => 'RKAP 2026',
            'status' => 'open',
        ]);

        $directorate = Directorate::create(['code' => 'DIR01', 'name' => 'Dir 1']);
        $department = Department::create(['directorate_id' => $directorate->id, 'code' => 'DEP01', 'name' => 'Dept 1']);
        $this->bureau = Bureau::create(['department_id' => $department->id, 'code' => 'BUR01', 'name' => 'Bureau 1']);

        $this->workPlanMaster = WorkPlan::create([
            'code' => 'WP001',
            'title' => 'Master Work Plan',
        ]);

        $this->coaMaster = Coa::create([
            'code' => '521111',
            'title' => 'Master COA',
        ]);
    }

    public function test_migration_upload_combines_duplicate_workplans_activities_and_coas(): void
    {
        $this->actingAs($this->admin);

        // Build CSV content with two rows that target the same submission, work plan, and COA
        $headers = [
            'submission_key', 'rkap_period_id', 'bureau_id', 'created_by',
            'work_plan_key', 'work_plan_id', 'activity_id', 'budget_item_key', 'coa_id',
            'bi_quantity', 'unit_price',
            'm1', 'm2', 'm3', 'm4', 'm5', 'm6', 'm7', 'm8', 'm9', 'm10', 'm11', 'm12',
            'co1', 'co2', 'co3', 'co4', 'co5', 'co6', 'co7', 'co8', 'co9', 'co10', 'co11', 'co12'
        ];

        $row1 = [
            'SUB01', $this->period->id, $this->bureau->id, $this->admin->id,
            'WPKEY1', $this->workPlanMaster->id, '', 'BIKEY1', $this->coaMaster->id,
            '2', '5000',
            '10000', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', // m1..m12
            '10000', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'  // co1..co12
        ];

        $row2 = [
            'SUB01', $this->period->id, $this->bureau->id, $this->admin->id,
            'WPKEY2', $this->workPlanMaster->id, '', 'BIKEY2', $this->coaMaster->id,
            '3', '10000',
            '0', '30000', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', // m1..m12
            '0', '30000', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'  // co1..co12
        ];

        $csvContent = implode(',', $headers) . "\n" . implode(',', $row1) . "\n" . implode(',', $row2);

        $file = UploadedFile::fake()->createWithContent('migration.csv', $csvContent);

        Livewire::test(DataMigrationUpload::class)
            ->set('file', $file)
            ->call('uploadAndImport')
            ->assertHasNoErrors()
            ->assertSet('imported', true);

        // Verify DB Consolidation
        // 1. Only one submission is created
        $this->assertEquals(1, RkapSubmission::count());
        $submission = RkapSubmission::first();
        $this->assertEquals($this->period->id, $submission->rkap_period_id);
        $this->assertEquals($this->bureau->id, $submission->bureau_id);

        // 2. Only one work plan is created (since it's the same work_plan_id & activity_id null in the same bureau)
        $this->assertEquals(1, RkapWorkPlan::count());
        $workPlan = RkapWorkPlan::first();
        $this->assertEquals($this->workPlanMaster->id, $workPlan->work_plan_id);
        $this->assertNull($workPlan->activity_id);

        // 3. Only one budget item is created (since it's the same COA)
        $this->assertEquals(1, RkapBudgetItem::count());
        $budgetItem = RkapBudgetItem::first();
        $this->assertEquals($this->coaMaster->code, $budgetItem->account_code);

        // 4. Quantity and Unit Price are correctly combined/recalculated
        // quantity = 2 + 3 = 5
        // total = 2 * 5000 + 3 * 10000 = 40000
        // unit_price = 40000 / 5 = 8000
        $this->assertEquals(5, $budgetItem->quantity);
        $this->assertEquals(8000, (float)$budgetItem->unit_price);
        $this->assertEquals(40000, (float)($budgetItem->quantity * $budgetItem->unit_price));

        // 5. Monthlies are consolidated (summed)
        $this->assertEquals(2, $budgetItem->monthlies()->count());
        $this->assertEquals(10000, (float)$budgetItem->monthlies()->where('month', 1)->first()->amount);
        $this->assertEquals(30000, (float)$budgetItem->monthlies()->where('month', 2)->first()->amount);

        // 6. Cash outs are consolidated (summed)
        $this->assertEquals(2, $budgetItem->cashOuts()->count());
        $this->assertEquals(10000, (float)$budgetItem->cashOuts()->where('month', 1)->first()->amount);
        $this->assertEquals(30000, (float)$budgetItem->cashOuts()->where('month', 2)->first()->amount);
    }
}
