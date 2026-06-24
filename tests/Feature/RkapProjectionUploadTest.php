<?php

namespace Tests\Feature;

use Tests\TestCase;
use Livewire\Livewire;
use App\Livewire\Rkap\RkapProjectionUpload;
use App\Models\RkapPeriod;
use App\Models\Bureau;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\User;
use App\Models\RkapSubmission;
use App\Models\RkapWorkPlan;
use App\Models\RkapBudgetItem;
use App\Models\RkapBudgetItemProjection;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

class RkapProjectionUploadTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $verifikatorUser;
    protected User $regularUser;
    protected RkapPeriod $period;
    protected RkapBudgetItem $budgetItem;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup Roles and Permissions
        $roleAdmin = Role::firstOrCreate(['name' => 'admin']);
        $roleVerifikator = Role::firstOrCreate(['name' => 'verifikator']);
        $roleUser = Role::firstOrCreate(['name' => 'user']);

        $permInput = Permission::firstOrCreate(['name' => 'rkap.projection.input', 'guard_name' => 'web']);
        $permView = Permission::firstOrCreate(['name' => 'rkap.projection.view', 'guard_name' => 'web']);

        $roleAdmin->givePermissionTo([$permInput, $permView]);
        $roleVerifikator->givePermissionTo([$permInput, $permView]);

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->adminUser->assignRole($roleAdmin);

        $this->verifikatorUser = User::create([
            'name' => 'Verifikator User',
            'email' => 'verifikator@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->verifikatorUser->assignRole($roleVerifikator);

        $this->regularUser = User::create([
            'name' => 'Regular User',
            'email' => 'regular@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->regularUser->assignRole($roleUser);

        // Setup Period and Submission
        $this->period = RkapPeriod::create([
            'year' => (int) date('Y'),
            'title' => 'RKAP ' . date('Y'),
            'status' => 'finalized',
            'submission_start' => now()->subDay(),
            'submission_end' => now()->addDay(),
        ]);

        $directorate = Directorate::create(['code' => 'DIR01', 'name' => 'Dir Test', 'is_active' => true]);
        $department = Department::create(['directorate_id' => $directorate->id, 'code' => 'DEP01', 'name' => 'Dept Test', 'is_active' => true]);
        $bureau = Bureau::create(['department_id' => $department->id, 'code' => 'BUR01', 'name' => 'Bur Test', 'is_active' => true]);

        $submission = RkapSubmission::create([
            'rkap_period_id' => $this->period->id,
            'bureau_id' => $bureau->id,
            'created_by' => $this->adminUser->id,
            'status' => 'approved',
            'total_budget' => 1000000,
        ]);

        $workPlan = RkapWorkPlan::create([
            'rkap_submission_id' => $submission->id,
            'program_code' => 'WP001',
            'program_name' => 'Work Plan Test',
            'quantity' => 1,
        ]);

        $this->budgetItem = RkapBudgetItem::create([
            'rkap_work_plan_id' => $workPlan->id,
            'account_code' => 'COA01',
            'description' => 'Budget Item Test',
            'quantity' => 1,
            'unit_price' => 1000000,
            'total_price' => 1000000,
        ]);

        // Monthly Budget limit is 80000 for each month (total = 960000 <= 1000000)
        for ($m = 1; $m <= 12; $m++) {
            $this->budgetItem->monthlies()->create([
                'month' => $m,
                'amount' => 80000,
            ]);
        }
    }

    public function test_regular_user_cannot_access_mass_upload_page(): void
    {
        $this->actingAs($this->regularUser);

        Livewire::test(RkapProjectionUpload::class)
            ->assertStatus(403);
    }

    public function test_admin_and_verifikator_can_access_mass_upload_page(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(RkapProjectionUpload::class)
            ->assertStatus(200)
            ->assertSee('Upload Massal Proyeksi RKAP');

        $this->actingAs($this->verifikatorUser);

        Livewire::test(RkapProjectionUpload::class)
            ->assertStatus(200)
            ->assertSee('Upload Massal Proyeksi RKAP');
    }

    public function test_valid_csv_upload_updates_projections(): void
    {
        $this->actingAs($this->adminUser);

        $currentMonth = (int) date('n');

        // Let's create a CSV that targets only future/current months
        $csvContent = "budget_item_id,yearly,m1,m2,m3,m4,m5,m6,m7,m8,m9,m10,m11,m12\n";
        $csvContent .= "{$this->budgetItem->id},0";
        for ($m = 1; $m <= 12; $m++) {
            // Put 50000 for current/future months, 0 for past months
            $csvContent .= "," . ($m >= $currentMonth ? "50000" : "0");
        }
        $csvContent .= "\n";

        $file = UploadedFile::fake()->createWithContent('projections.csv', $csvContent);

        Livewire::test(RkapProjectionUpload::class)
            ->set('periodId', $this->period->id)
            ->set('file', $file)
            ->call('uploadAndImport')
            ->assertHasNoErrors()
            ->assertSet('imported', true);

        // Verify database contains updated projection
        $projection = RkapBudgetItemProjection::where('rkap_budget_item_id', $this->budgetItem->id)
            ->where('month', $currentMonth)
            ->first();

        $this->assertNotNull($projection);
        $this->assertEquals(50000.00, (float)$projection->amount);

        // Verify budget item projection was synced
        $this->budgetItem->refresh();
        $expectedSum = 50000 * (12 - $currentMonth + 1);
        $this->assertEquals($expectedSum, (float)$this->budgetItem->projection);
    }

    public function test_upload_monthly_projection_can_exceed_monthly_budget_plan(): void
    {
        $this->actingAs($this->adminUser);

        $currentMonth = (int) date('n');

        // Limit is 80000. Let's send 90000 for the current month. It should succeed!
        $csvContent = "budget_item_id,yearly,m1,m2,m3,m4,m5,m6,m7,m8,m9,m10,m11,m12\n";
        $csvContent .= "{$this->budgetItem->id},0";
        for ($m = 1; $m <= 12; $m++) {
            $csvContent .= "," . ($m === $currentMonth ? "90000" : "0");
        }
        $csvContent .= "\n";

        $file = UploadedFile::fake()->createWithContent('projections.csv', $csvContent);

        Livewire::test(RkapProjectionUpload::class)
            ->set('periodId', $this->period->id)
            ->set('file', $file)
            ->call('uploadAndImport')
            ->assertHasNoErrors()
            ->assertSet('imported', true);

        // Verify database contains updated projection
        $projection = RkapBudgetItemProjection::where('rkap_budget_item_id', $this->budgetItem->id)
            ->where('month', $currentMonth)
            ->first();

        $this->assertNotNull($projection);
        $this->assertEquals(90000.00, (float)$projection->amount);
    }

    public function test_upload_total_projection_can_exceed_total_budget(): void
    {
        $this->actingAs($this->adminUser);

        $currentMonth = (int) date('n');

        // Upload CSV with 1,050,000 for current month and 0 for others. Total budget is 1,000,000. It should succeed!
        $csvContent = "budget_item_id,yearly,m1,m2,m3,m4,m5,m6,m7,m8,m9,m10,m11,m12\n";
        $csvContent .= "{$this->budgetItem->id},0";
        for ($m = 1; $m <= 12; $m++) {
            $csvContent .= "," . ($m === $currentMonth ? "1050000" : "0");
        }
        $csvContent .= "\n";

        $file = UploadedFile::fake()->createWithContent('projections.csv', $csvContent);

        Livewire::test(RkapProjectionUpload::class)
            ->set('periodId', $this->period->id)
            ->set('file', $file)
            ->call('uploadAndImport')
            ->assertHasNoErrors()
            ->assertSet('imported', true);

        // Verify database contains updated projection
        $projection = RkapBudgetItemProjection::where('rkap_budget_item_id', $this->budgetItem->id)
            ->where('month', $currentMonth)
            ->first();

        $this->assertNotNull($projection);
        $this->assertEquals(1050000.00, (float)$projection->amount);
    }

    public function test_fails_if_past_month_projection_is_modified(): void
    {
        $this->actingAs($this->adminUser);

        $currentMonth = (int) date('n');

        // Skip test if it is January (no past months)
        if ($currentMonth === 1) {
            $this->assertTrue(true);
            return;
        }

        $pastMonth = $currentMonth - 1;

        // Seed some projection in past month
        RkapBudgetItemProjection::create([
            'rkap_budget_item_id' => $this->budgetItem->id,
            'rkap_period_id' => $this->period->id,
            'month' => $pastMonth,
            'amount' => 10000,
            'inputted_by' => $this->adminUser->id,
        ]);
        $this->budgetItem->update(['projection' => 10000]);

        // Upload CSV with different amount (e.g. 15000) for that past month
        $csvContent = "budget_item_id,yearly,m1,m2,m3,m4,m5,m6,m7,m8,m9,m10,m11,m12\n";
        $csvContent .= "{$this->budgetItem->id},0";
        for ($m = 1; $m <= 12; $m++) {
            if ($m === $pastMonth) {
                $csvContent .= ",15000";
            } else {
                $csvContent .= ",0";
            }
        }
        $csvContent .= "\n";

        $file = UploadedFile::fake()->createWithContent('projections.csv', $csvContent);

        Livewire::test(RkapProjectionUpload::class)
            ->set('periodId', $this->period->id)
            ->set('file', $file)
            ->call('uploadAndImport')
            ->assertSet('imported', false)
            ->assertSee('tidak dapat diubah karena merupakan bulan yang sudah lewat');
    }

    public function test_valid_yearly_projection_upload_updates_projection_column_directly(): void
    {
        $this->actingAs($this->adminUser);

        $csvContent = "budget_item_id,yearly,m1,m2,m3,m4,m5,m6,m7,m8,m9,m10,m11,m12\n";
        $csvContent .= "{$this->budgetItem->id},450000,0,0,0,0,0,0,0,0,0,0,0,0\n";

        $file = UploadedFile::fake()->createWithContent('projections.csv', $csvContent);

        Livewire::test(RkapProjectionUpload::class)
            ->set('periodId', $this->period->id)
            ->set('file', $file)
            ->call('uploadAndImport')
            ->assertHasNoErrors()
            ->assertSet('imported', true);

        // Verify budget item projection column directly
        $this->budgetItem->refresh();
        $this->assertEquals(450000.00, (float)$this->budgetItem->projection);

        // Verify count of monthly projections is 0
        $savedProjectionsCount = RkapBudgetItemProjection::where('rkap_budget_item_id', $this->budgetItem->id)->count();
        $this->assertEquals(0, $savedProjectionsCount);
    }

    public function test_upload_yearly_projection_can_exceed_total_price(): void
    {
        $this->actingAs($this->adminUser);

        // Total price is 1000000. Let's upload 1200000. It should succeed!
        $csvContent = "budget_item_id,yearly,m1,m2,m3,m4,m5,m6,m7,m8,m9,m10,m11,m12\n";
        $csvContent .= "{$this->budgetItem->id},1200000,0,0,0,0,0,0,0,0,0,0,0,0\n";

        $file = UploadedFile::fake()->createWithContent('projections.csv', $csvContent);

        Livewire::test(RkapProjectionUpload::class)
            ->set('periodId', $this->period->id)
            ->set('file', $file)
            ->call('uploadAndImport')
            ->assertHasNoErrors()
            ->assertSet('imported', true);

        // Verify budget item projection column directly
        $this->budgetItem->refresh();
        $this->assertEquals(1200000.00, (float)$this->budgetItem->projection);
    }

    public function test_upload_yearly_fails_if_item_already_has_monthly_projections(): void
    {
        $this->actingAs($this->adminUser);

        // Seed monthly projection
        RkapBudgetItemProjection::create([
            'rkap_budget_item_id' => $this->budgetItem->id,
            'rkap_period_id' => $this->period->id,
            'month' => 5,
            'amount' => 30000,
            'inputted_by' => $this->adminUser->id,
        ]);
        $this->budgetItem->update(['projection' => 30000]);

        $csvContent = "budget_item_id,yearly,m1,m2,m3,m4,m5,m6,m7,m8,m9,m10,m11,m12\n";
        $csvContent .= "{$this->budgetItem->id},40000,0,0,0,0,0,0,0,0,0,0,0,0\n";

        $file = UploadedFile::fake()->createWithContent('projections.csv', $csvContent);

        Livewire::test(RkapProjectionUpload::class)
            ->set('periodId', $this->period->id)
            ->set('file', $file)
            ->call('uploadAndImport')
            ->assertSet('imported', false)
            ->assertSee('Tidak dapat mengisi proyeksi tahunan karena item ini sudah diatur dengan proyeksi bulanan');
    }

    public function test_upload_monthly_fails_if_item_already_has_yearly_projection(): void
    {
        $this->actingAs($this->adminUser);

        // Seed yearly projection in database directly on the item
        $this->budgetItem->update(['projection' => 50000]);

        $currentMonth = (int) date('n');
        $csvContent = "budget_item_id,yearly,m1,m2,m3,m4,m5,m6,m7,m8,m9,m10,m11,m12\n";
        $csvContent .= "{$this->budgetItem->id},0";
        for ($m = 1; $m <= 12; $m++) {
            $csvContent .= "," . ($m === $currentMonth ? "50000" : "0");
        }
        $csvContent .= "\n";

        $file = UploadedFile::fake()->createWithContent('projections.csv', $csvContent);

        Livewire::test(RkapProjectionUpload::class)
            ->set('periodId', $this->period->id)
            ->set('file', $file)
            ->call('uploadAndImport')
            ->assertSet('imported', false)
            ->assertSee('Tidak dapat mengisi proyeksi bulanan karena item ini sudah diatur dengan proyeksi tahunan');
    }

    public function test_empty_cells_in_csv_are_treated_as_zero_without_validation_errors(): void
    {
        $this->actingAs($this->adminUser);

        // Upload CSV with empty/missing cells for m1-m12 and yearly
        $csvContent = "budget_item_id,yearly,m1,m2,m3,m4,m5,m6,m7,m8,m9,m10,m11,m12\n";
        $csvContent .= "{$this->budgetItem->id},,,,,,,,,,,,,\n"; // all cells are blank

        $file = UploadedFile::fake()->createWithContent('projections.csv', $csvContent);

        Livewire::test(RkapProjectionUpload::class)
            ->set('periodId', $this->period->id)
            ->set('file', $file)
            ->call('uploadAndImport')
            ->assertHasNoErrors()
            ->assertSet('imported', true);

        // Verify budget item projection is 0
        $this->budgetItem->refresh();
        $this->assertEquals(0, (float)$this->budgetItem->projection);

        // Verify count of monthly projections is 0
        $savedProjectionsCount = RkapBudgetItemProjection::where('rkap_budget_item_id', $this->budgetItem->id)->count();
        $this->assertEquals(0, $savedProjectionsCount);
    }

    public function test_negative_projection_upload_is_allowed(): void
    {
        $this->actingAs($this->adminUser);

        $currentMonth = (int) date('n');
        $csvContent = "budget_item_id,yearly,m1,m2,m3,m4,m5,m6,m7,m8,m9,m10,m11,m12\n";
        $csvContent .= "{$this->budgetItem->id},-15000";
        for ($m = 1; $m <= 12; $m++) {
            $csvContent .= "," . ($m === $currentMonth ? "-15000" : "0");
        }
        $csvContent .= "\n";

        $file = UploadedFile::fake()->createWithContent('projections.csv', $csvContent);

        Livewire::test(RkapProjectionUpload::class)
            ->set('periodId', $this->period->id)
            ->set('file', $file)
            ->call('uploadAndImport')
            ->assertHasNoErrors()
            ->assertSet('imported', true);

        // Verify database contains updated negative projection
        $projection = RkapBudgetItemProjection::where('rkap_budget_item_id', $this->budgetItem->id)
            ->where('month', $currentMonth)
            ->first();

        $this->assertNotNull($projection);
        $this->assertEquals(-15000.00, (float)$projection->amount);

        $this->budgetItem->refresh();
        $this->assertEquals(-15000.00, (float)$this->budgetItem->projection);
    }
}
