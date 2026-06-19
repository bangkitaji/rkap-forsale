<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\RkapPeriod;
use App\Models\Bureau;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\User;
use App\Models\RkapSubmission;
use App\Models\RkapWorkPlan;
use App\Models\RkapBudgetItem;
use App\Models\RkapBudgetItemRealization;
use App\Models\RkapBudgetItemProjection;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RkapDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $kabiro1;
    protected User $kabiro2;
    protected RkapPeriod $period;
    protected Bureau $bureau1;
    protected Bureau $bureau2;
    protected RkapSubmission $submission1;
    protected RkapSubmission $submission2;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Roles & Permissions setup
        Role::firstOrCreate(['name' => 'admin']);
        
        $roleKabiro = Role::firstOrCreate(['name' => 'kepala_biro']);
        $roleKabiro->givePermissionTo(Permission::firstOrCreate(['name' => 'rkap.show', 'guard_name' => 'web']));

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->admin->assignRole('admin');

        // 2. Setup active Period
        $this->period = RkapPeriod::create([
            'year' => (int) date('Y'),
            'title' => 'RKAP ' . date('Y'),
            'status' => 'finalized',
            'submission_start' => now()->subDay(),
            'submission_end' => now()->addDay(),
        ]);

        // 3. Organization Setup
        $directorate = Directorate::create(['code' => 'D1', 'name' => 'Dir 1']);
        $department = Department::create(['directorate_id' => $directorate->id, 'code' => 'DP1', 'name' => 'Dept 1']);
        
        $this->bureau1 = Bureau::create(['department_id' => $department->id, 'code' => 'B1', 'name' => 'Bur 1']);
        $this->bureau2 = Bureau::create(['department_id' => $department->id, 'code' => 'B2', 'name' => 'Bur 2']);

        $this->kabiro1 = User::create([
            'name' => 'Kabiro 1',
            'email' => 'kabiro1@example.com',
            'password' => bcrypt('password'),
            'bureau_id' => $this->bureau1->id,
        ]);
        $this->kabiro1->assignRole($roleKabiro);

        $this->kabiro2 = User::create([
            'name' => 'Kabiro 2',
            'email' => 'kabiro2@example.com',
            'password' => bcrypt('password'),
            'bureau_id' => $this->bureau2->id,
        ]);
        $this->kabiro2->assignRole($roleKabiro);

        // 4. Submissions & Budgets
        $this->submission1 = RkapSubmission::create([
            'rkap_period_id' => $this->period->id,
            'bureau_id' => $this->bureau1->id,
            'created_by' => $this->kabiro1->id,
            'status' => 'approved',
            'total_budget' => 60000,
        ]);

        $this->submission2 = RkapSubmission::create([
            'rkap_period_id' => $this->period->id,
            'bureau_id' => $this->bureau2->id,
            'created_by' => $this->kabiro2->id,
            'status' => 'approved',
            'total_budget' => 40000,
        ]);

        // Mock work plans and items
        $wp1 = RkapWorkPlan::create([
            'rkap_submission_id' => $this->submission1->id,
            'program_code' => 'WP1',
            'program_name' => 'WP1',
        ]);
        $bi1 = RkapBudgetItem::create([
            'rkap_work_plan_id' => $wp1->id,
            'account_code' => '521111',
            'description' => 'Item 1',
            'quantity' => 1,
            'unit_price' => 60000,
        ]);

        $wp2 = RkapWorkPlan::create([
            'rkap_submission_id' => $this->submission2->id,
            'program_code' => 'WP2',
            'program_name' => 'WP2',
        ]);
        $bi2 = RkapBudgetItem::create([
            'rkap_work_plan_id' => $wp2->id,
            'account_code' => '522222',
            'description' => 'Item 2',
            'quantity' => 1,
            'unit_price' => 40000,
        ]);

        // Add Realization & Projection for Bureau 1
        RkapBudgetItemRealization::create([
            'rkap_budget_item_id' => $bi1->id,
            'rkap_period_id' => $this->period->id,
            'month' => 3,
            'amount' => 15000,
            'uploaded_by' => $this->admin->id,
            'uploaded_at' => now(),
        ]);
        RkapBudgetItemProjection::create([
            'rkap_budget_item_id' => $bi1->id,
            'rkap_period_id' => $this->period->id,
            'month' => 12,
            'amount' => 50000,
            'inputted_by' => $this->kabiro1->id,
        ]);

        // Add Realization & Projection for Bureau 2
        RkapBudgetItemRealization::create([
            'rkap_budget_item_id' => $bi2->id,
            'rkap_period_id' => $this->period->id,
            'month' => 4,
            'amount' => 10000,
            'uploaded_by' => $this->admin->id,
            'uploaded_at' => now(),
        ]);
        RkapBudgetItemProjection::create([
            'rkap_budget_item_id' => $bi2->id,
            'rkap_period_id' => $this->period->id,
            'month' => 12,
            'amount' => 38000,
            'inputted_by' => $this->kabiro2->id,
        ]);
    }

    public function test_guest_is_redirected(): void
    {
        $response = $this->get('/analytics');
        $response->assertRedirect('/login');
    }

    public function test_admin_sees_aggregated_statistics(): void
    {
        $response = $this->actingAs($this->admin)->get('/analytics');

        $response->assertStatus(200);
        $response->assertViewHas('activePeriod');
        $response->assertViewHas('stats');

        $stats = $response->viewData('stats');
        
        // Sum of both submission budgets (60000 + 40000)
        $this->assertEquals(100000.0, $stats['total_budget']);
        
        // Sum of both realizations (15000 + 10000)
        $this->assertEquals(25000.0, $stats['total_realization']);
        
        // Sum of both projections (50000 + 38000)
        $this->assertEquals(88000.0, $stats['total_projection']);

        // Absorption Rate: (25000 / 100000) * 100 = 25%
        $this->assertEquals(25.0, $stats['absorption_rate']);

        // Outlook Rate: (88000 / 100000) * 100 = 88%
        $this->assertEquals(88.0, $stats['outlook_rate']);
    }

    public function test_kabiro_scoping_limits_data(): void
    {
        $response = $this->actingAs($this->kabiro1)->get('/analytics');

        $response->assertStatus(200);
        $stats = $response->viewData('stats');

        // Only Bureau 1 budget (60000)
        $this->assertEquals(60000.0, $stats['total_budget']);
        
        // Only Bureau 1 realization (15000)
        $this->assertEquals(15000.0, $stats['total_realization']);
        
        // Only Bureau 1 projection (50000)
        $this->assertEquals(50000.0, $stats['total_projection']);

        // Absorption Rate: (15000 / 60000) * 100 = 25%
        $this->assertEquals(25.0, $stats['absorption_rate']);

        // Outlook Rate: (50000 / 60000) * 100 = 83.3%
        $this->assertEquals(83.3, $stats['outlook_rate']);
    }

    public function test_admin_sees_profit_and_loss_summary(): void
    {
        // 1. Create COAs mapped to categories
        $coaGroup = \App\Models\CoaGroup::create([
            'code' => '500000',
            'name' => 'Biaya Operasional',
        ]);

        $catRevenue = \App\Models\CoaCategory::where('key', 'revenue_passenger')->first();
        $catCost = \App\Models\CoaCategory::where('key', 'direct_cost_traction')->first();

        \App\Models\Coa::create([
            'coa_group_id' => $coaGroup->id,
            'code' => '521111',
            'title' => 'Tiket KA',
            'coa_category_id' => $catRevenue->id,
        ]);

        \App\Models\Coa::create([
            'coa_group_id' => $coaGroup->id,
            'code' => '522222',
            'title' => 'Energi Traksi',
            'coa_category_id' => $catCost->id,
        ]);

        $response = $this->actingAs($this->admin)->get('/analytics');

        $response->assertStatus(200);
        $response->assertViewHas('plGroups');
        $response->assertViewHas('plSummary');

        $plGroups = $response->viewData('plGroups');
        $plSummary = $response->viewData('plSummary');

        // Check revenue is correct
        // bi1 (60000) is mapped to revenue_passenger via 521111
        $this->assertEquals(60000.0, $plGroups['Revenue']['budget_subtotal']);

        // bi2 (40000) is mapped to direct_cost_traction via 522222
        $this->assertEquals(40000.0, $plGroups['Direct Cost']['budget_subtotal']);

        // Gross Profit: Revenue (60000) - Direct Cost (40000) = 20000
        $this->assertEquals(20000.0, $plSummary['gross_profit']['budget']);

        // Check if values are rendered in the HTML
        $response->assertSee('Laporan Laba Rugi');
        $response->assertSee('Ringkasan Laba Rugi');
        $response->assertSee('Pendapatan');
        $response->assertSee('Beban Langsung');
        $response->assertSee('Laba Kotor');
        $response->assertSee('Laba Bersih');
        $response->assertSee('Pendapatan Tiket Penumpang');
        $response->assertSee('Beban Energi Listrik Traksi');
        $response->assertSee('Laba Kotor (Gross Profit)');
        $response->assertSee('Laba Usaha (EBITDA)');
        $response->assertSee('Laba Bersih (Net Profit)');
    }

    public function test_direksi_sees_pending_reviews_in_tugas_saya(): void
    {
        // Create role Direksi
        $roleDireksi = Role::firstOrCreate(['name' => 'direksi']);
        $roleDireksi->givePermissionTo(Permission::firstOrCreate(['name' => 'rkap.show', 'guard_name' => 'web']));

        // Create a Directorate and Department
        $directorate = Directorate::create(['code' => 'DIR_TEST', 'name' => 'Directorate Test']);
        $department = Department::create(['directorate_id' => $directorate->id, 'code' => 'DEPT_TEST', 'name' => 'Department Test']);
        $bureau = Bureau::create(['department_id' => $department->id, 'code' => 'BUR_TEST', 'name' => 'Bureau Test']);

        // Create a Direksi User
        $direksiUser = User::create([
            'name' => 'Direksi User Test',
            'email' => 'direksitest@example.com',
            'password' => bcrypt('password'),
            'directorate_id' => $directorate->id,
        ]);
        $direksiUser->assignRole($roleDireksi);

        // Create a submission with status 'dir_review'
        $submission = RkapSubmission::create([
            'rkap_period_id' => $this->period->id,
            'bureau_id' => $bureau->id,
            'created_by' => $this->kabiro1->id,
            'status' => 'dir_review',
            'total_budget' => 50000,
        ]);

        $this->actingAs($direksiUser);

        // Test the Livewire component RkapDashboard
        \Livewire\Livewire::test(\App\Livewire\Rkap\RkapDashboard::class)
            ->assertStatus(200)
            ->assertViewHas('myActions', function ($myActions) use ($submission) {
                return $myActions->contains('id', $submission->id);
            });
    }

    public function test_verifikator_sees_pending_reviews_in_tugas_saya(): void
    {
        // Create role Verifikator
        $roleVerifikator = Role::firstOrCreate(['name' => 'verifikator']);
        $roleVerifikator->givePermissionTo(Permission::firstOrCreate(['name' => 'rkap.show', 'guard_name' => 'web']));

        // Create a Directorate and Department
        $directorate = Directorate::create(['code' => 'DIR_TEST_V', 'name' => 'Directorate Test V']);
        $department = Department::create(['directorate_id' => $directorate->id, 'code' => 'DEPT_TEST_V', 'name' => 'Department Test V']);
        $bureau = Bureau::create(['department_id' => $department->id, 'code' => 'BUR_TEST_V', 'name' => 'Bureau Test V']);

        // Create a Verifikator User
        $verifikatorUser = User::create([
            'name' => 'Verifikator User Test',
            'email' => 'verifikatortest@example.com',
            'password' => bcrypt('password'),
        ]);
        $verifikatorUser->assignRole($roleVerifikator);

        // Create a submission with status 'final_review'
        $submission = RkapSubmission::create([
            'rkap_period_id' => $this->period->id,
            'bureau_id' => $bureau->id,
            'created_by' => $this->kabiro1->id,
            'status' => 'final_review',
            'total_budget' => 50000,
        ]);

        $this->actingAs($verifikatorUser);

        // Test the Livewire component RkapDashboard
        \Livewire\Livewire::test(\App\Livewire\Rkap\RkapDashboard::class)
            ->assertStatus(200)
            ->assertViewHas('myActions', function ($myActions) use ($submission) {
                return $myActions->contains('id', $submission->id);
            });
    }

    public function test_kadept_sees_scoped_analytics_data(): void
    {
        $roleKadept = Role::firstOrCreate(['name' => 'kepala_departemen']);
        $roleKadept->givePermissionTo(Permission::firstOrCreate(['name' => 'dashboard.show', 'guard_name' => 'web']));

        $department = Department::where('code', 'DP1')->first();
        $directorate = Directorate::where('code', 'D1')->first();

        // Create Kepala Departemen user for DP1 department
        $kadeptUser = User::create([
            'name' => 'Kadept Scoped Test',
            'email' => 'kadeptscoped@example.com',
            'password' => bcrypt('password'),
            'department_id' => $department->id,
            'directorate_id' => $directorate->id,
        ]);
        $kadeptUser->assignRole($roleKadept);

        // Create a different department and bureau with an approved submission
        $otherDept = Department::create(['directorate_id' => $directorate->id, 'code' => 'DP2', 'name' => 'Dept 2']);
        $otherBureau = Bureau::create(['department_id' => $otherDept->id, 'code' => 'B3', 'name' => 'Bur 3']);
        RkapSubmission::create([
            'rkap_period_id' => $this->period->id,
            'bureau_id' => $otherBureau->id,
            'created_by' => $this->kabiro1->id,
            'status' => 'approved',
            'total_budget' => 50000,
        ]);

        // Access the analytics dashboard as Kepala Departemen
        $response = $this->actingAs($kadeptUser)->get('/analytics');

        $response->assertStatus(200);
        $stats = $response->viewData('stats');

        // Total budget should only be the sum of bureau1 and bureau2 budgets (60000 + 40000 = 100000)
        // and should exclude the other department's budget (50000)
        $this->assertEquals(100000.0, $stats['total_budget']);

        // Assert that P&L summary and table are not displayed
        $response->assertDontSee('Ringkasan Laba Rugi');
        $response->assertDontSee('Laporan Laba Rugi');

        // Assert comparative data options
        $deptData = $response->viewData('departmentData');
        $burData = $response->viewData('bureauData');

        // Kadept should only see their own department DP1
        $this->assertCount(1, $deptData);
        $this->assertEquals('Dept 1', $deptData[0]['label']);

        // Kadept should see their bureaus: Bur 1 and Bur 2
        $this->assertCount(2, $burData);
        $bureauLabels = collect($burData)->pluck('label')->toArray();
        $this->assertContains('Bur 1', $bureauLabels);
        $this->assertContains('Bur 2', $bureauLabels);
    }

    public function test_direksi_and_direktur_utama_see_global_analytics_data(): void
    {
        $roleDireksi = Role::firstOrCreate(['name' => 'direksi']);
        $roleDireksi->givePermissionTo(Permission::firstOrCreate(['name' => 'dashboard.show', 'guard_name' => 'web']));
        
        $roleDirut = Role::firstOrCreate(['name' => 'direktur_utama']);
        $roleDirut->givePermissionTo(Permission::firstOrCreate(['name' => 'dashboard.show', 'guard_name' => 'web']));

        $directorate = Directorate::where('code', 'D1')->first();

        // Create Direksi User
        $direksiUser = User::create([
            'name' => 'Direksi Global Test',
            'email' => 'direksiglobal@example.com',
            'password' => bcrypt('password'),
            'directorate_id' => $directorate->id,
        ]);
        $direksiUser->assignRole($roleDireksi);

        // Create Direktur Utama User
        $dirutUser = User::create([
            'name' => 'Dirut Global Test',
            'email' => 'dirutglobal@example.com',
            'password' => bcrypt('password'),
        ]);
        $dirutUser->assignRole($roleDirut);

        // Create another department in a different directorate to ensure it's global
        $otherDirectorate = Directorate::create(['code' => 'D2', 'name' => 'Dir 2']);
        $otherDept = Department::create(['directorate_id' => $otherDirectorate->id, 'code' => 'DP2', 'name' => 'Dept 2']);
        $otherBureau = Bureau::create(['department_id' => $otherDept->id, 'code' => 'B3', 'name' => 'Bur 3']);
        RkapSubmission::create([
            'rkap_period_id' => $this->period->id,
            'bureau_id' => $otherBureau->id,
            'created_by' => $this->kabiro1->id,
            'status' => 'approved',
            'total_budget' => 50000,
        ]);

        // 1. Assert Direksi User sees global data (60000 + 40000 + 50000 = 150000)
        $response1 = $this->actingAs($direksiUser)->get('/analytics');
        $response1->assertStatus(200);
        $stats1 = $response1->viewData('stats');
        $this->assertEquals(150000.0, $stats1['total_budget']);

        // 2. Assert Direktur Utama User sees global data (150000)
        $response2 = $this->actingAs($dirutUser)->get('/analytics');
        $response2->assertStatus(200);
        $stats2 = $response2->viewData('stats');
        $this->assertEquals(150000.0, $stats2['total_budget']);
    }

    public function test_kabiro_sees_draft_and_revision_submissions_in_tugas_saya(): void
    {
        // 1. Change existing submission1 to draft status
        $this->submission1->update(['status' => 'draft']);

        // 2. Create a new bureau and a revision submission for it in the same period
        $roleKabiro = Role::where('name', 'kepala_biro')->first();
        $department = Department::first();
        $bureau3 = Bureau::create(['department_id' => $department->id, 'code' => 'B3', 'name' => 'Bur 3']);
        $kabiro3 = User::create([
            'name' => 'Kabiro 3',
            'email' => 'kabiro3@example.com',
            'password' => bcrypt('password'),
            'bureau_id' => $bureau3->id,
        ]);
        $kabiro3->assignRole($roleKabiro);

        $submissionRevision = RkapSubmission::create([
            'rkap_period_id' => $this->period->id,
            'bureau_id' => $bureau3->id,
            'created_by' => $kabiro3->id,
            'status' => 'dept_revision',
            'total_budget' => 25000,
        ]);

        // 3. Test that kabiro1 sees their draft submission
        $this->actingAs($this->kabiro1);
        \Livewire\Livewire::test(\App\Livewire\Rkap\RkapDashboard::class)
            ->assertStatus(200)
            ->assertViewHas('myActions', function ($myActions) {
                return $myActions->contains('id', $this->submission1->id);
            });

        // 4. Test that kabiro3 sees their revision submission
        $this->actingAs($kabiro3);
        \Livewire\Livewire::test(\App\Livewire\Rkap\RkapDashboard::class)
            ->assertStatus(200)
            ->assertViewHas('myActions', function ($myActions) use ($submissionRevision) {
                return $myActions->contains('id', $submissionRevision->id);
            });
    }

    public function test_user_can_select_finalized_rkap_period_on_analytics_dashboard(): void
    {
        // Create an additional finalized period for previous year (e.g. 2025)
        $previousYearPeriod = RkapPeriod::create([
            'year' => 2025,
            'title' => 'RKAP 2025',
            'status' => 'finalized',
            'submission_start' => now()->subYear(),
            'submission_end' => now()->subYear()->addMonth(),
        ]);

        // Create an approved submission for the previous year period
        $submission2025 = RkapSubmission::create([
            'rkap_period_id' => $previousYearPeriod->id,
            'bureau_id' => $this->bureau1->id,
            'created_by' => $this->kabiro1->id,
            'status' => 'approved',
            'total_budget' => 75000,
        ]);

        // Access the analytics dashboard as admin
        $response = $this->actingAs($this->admin)->get('/analytics');

        $response->assertStatus(200);
        $response->assertViewHas('finalizedPeriods');
        $response->assertSee('RKAP ' . date('Y'));
        $response->assertSee('RKAP 2025');

        // Assert cumulative realization on current year period
        $cumulativeRealizationCurrent = $response->viewData('cumulativeRealization');
        $currentMonth = (int) date('n');
        $this->assertCount(12, $cumulativeRealizationCurrent);
        for ($i = 0; $i < $currentMonth; $i++) {
            $this->assertNotNull($cumulativeRealizationCurrent[$i]);
        }
        for ($i = $currentMonth; $i < 12; $i++) {
            $this->assertNull($cumulativeRealizationCurrent[$i]);
        }

        // Request with period_id of the previous year period
        $responseWithPeriod = $this->actingAs($this->admin)->get('/analytics?period_id=' . $previousYearPeriod->id);

        $responseWithPeriod->assertStatus(200);
        $stats = $responseWithPeriod->viewData('stats');
        // Total budget should be the sum of submissions in 2025 (75000)
        $this->assertEquals(75000.0, $stats['total_budget']);
        $responseWithPeriod->assertSee('Menampilkan visualisasi data untuk periode: <strong>RKAP 2025</strong>', false);

        // Assert cumulative realization on past year period (all 12 months should be non-null)
        $cumulativeRealization2025 = $responseWithPeriod->viewData('cumulativeRealization');
        $this->assertCount(12, $cumulativeRealization2025);
        foreach ($cumulativeRealization2025 as $val) {
            $this->assertNotNull($val);
        }
    }

    public function test_annual_rkap_comparison_chart_data(): void
    {
        $currentYear = (int) date('Y');

        // Create last year's period and next year's period (current year period already exists in setUp)
        $lastYearPeriod = RkapPeriod::create([
            'year' => $currentYear - 1,
            'title' => 'RKAP ' . ($currentYear - 1),
            'status' => 'finalized',
            'submission_start' => now()->subYear(),
            'submission_end' => now()->subYear()->addMonth(),
        ]);

        $nextYearPeriod = RkapPeriod::create([
            'year' => $currentYear + 1,
            'title' => 'RKAP ' . ($currentYear + 1),
            'status' => 'finalized',
            'submission_start' => now()->addYear(),
            'submission_end' => now()->addYear()->addMonth(),
        ]);

        // Create approved submissions, work plans, items, realizations, projections for last year
        $subLastYear = RkapSubmission::create([
            'rkap_period_id' => $lastYearPeriod->id,
            'bureau_id' => $this->bureau1->id,
            'created_by' => $this->kabiro1->id,
            'status' => 'approved',
            'total_budget' => 50000,
        ]);
        $wpLast = RkapWorkPlan::create([
            'rkap_submission_id' => $subLastYear->id,
            'program_code' => 'WPLAST',
            'program_name' => 'WPLAST',
        ]);
        $biLast = RkapBudgetItem::create([
            'rkap_work_plan_id' => $wpLast->id,
            'account_code' => '521111',
            'description' => 'Last Year Item',
            'quantity' => 1,
            'unit_price' => 50000,
        ]);
        RkapBudgetItemRealization::create([
            'rkap_budget_item_id' => $biLast->id,
            'rkap_period_id' => $lastYearPeriod->id,
            'month' => 6,
            'amount' => 30000,
            'uploaded_by' => $this->admin->id,
            'uploaded_at' => now(),
        ]);
        RkapBudgetItemProjection::create([
            'rkap_budget_item_id' => $biLast->id,
            'rkap_period_id' => $lastYearPeriod->id,
            'month' => 12,
            'amount' => 45000,
            'inputted_by' => $this->kabiro1->id,
        ]);

        // Create draft submissions, work plans, items, realizations, projections for next year
        // We set status = 'draft' to verify that comparison chart includes next year data even when status is not approved
        $subNextYear = RkapSubmission::create([
            'rkap_period_id' => $nextYearPeriod->id,
            'bureau_id' => $this->bureau1->id,
            'created_by' => $this->kabiro1->id,
            'status' => 'draft',
            'total_budget' => 90000,
        ]);
        $wpNext = RkapWorkPlan::create([
            'rkap_submission_id' => $subNextYear->id,
            'program_code' => 'WPNEXT',
            'program_name' => 'WPNEXT',
        ]);
        $biNext = RkapBudgetItem::create([
            'rkap_work_plan_id' => $wpNext->id,
            'account_code' => '521111',
            'description' => 'Next Year Item',
            'quantity' => 1,
            'unit_price' => 90000,
        ]);
        RkapBudgetItemRealization::create([
            'rkap_budget_item_id' => $biNext->id,
            'rkap_period_id' => $nextYearPeriod->id,
            'month' => 6,
            'amount' => 10000,
            'uploaded_by' => $this->admin->id,
            'uploaded_at' => now(),
        ]);
        RkapBudgetItemProjection::create([
            'rkap_budget_item_id' => $biNext->id,
            'rkap_period_id' => $nextYearPeriod->id,
            'month' => 12,
            'amount' => 85000,
            'inputted_by' => $this->kabiro1->id,
        ]);

        // 1. Assert admin (global view) sees aggregated data for all three years
        $responseAdmin = $this->actingAs($this->admin)->get('/analytics');
        $responseAdmin->assertStatus(200);
        $responseAdmin->assertViewHas('comparisonData');
        $responseAdmin->assertSee('Komparasi RKAP Antar Tahun');
        $responseAdmin->assertSee('id="annualComparisonChart"', false);

        $compDataAdmin = $responseAdmin->viewData('comparisonData');
        $this->assertCount(3, $compDataAdmin);

        // Last Year details (index 0)
        $this->assertEquals($currentYear - 1, $compDataAdmin[0]['year']);
        $this->assertEquals(($currentYear - 1) . ' (Tahun Lalu)', $compDataAdmin[0]['label']);
        $this->assertEquals(50000.0, $compDataAdmin[0]['budget']);
        $this->assertEquals(30000.0, $compDataAdmin[0]['realization']);
        $this->assertEquals(45000.0, $compDataAdmin[0]['projection']);

        // Current Year details (index 1)
        $this->assertEquals($currentYear, $compDataAdmin[1]['year']);
        $this->assertEquals($currentYear . ' (Tahun Berjalan)', $compDataAdmin[1]['label']);
        $this->assertEquals(100000.0, $compDataAdmin[1]['budget']);
        $this->assertEquals(25000.0, $compDataAdmin[1]['realization']);
        $this->assertEquals(88000.0, $compDataAdmin[1]['projection']);

        // Next Year details (index 2)
        $this->assertEquals($currentYear + 1, $compDataAdmin[2]['year']);
        $this->assertEquals(($currentYear + 1) . ' (Tahun Depan)', $compDataAdmin[2]['label']);
        $this->assertEquals(90000.0, $compDataAdmin[2]['budget']);
        $this->assertEquals(10000.0, $compDataAdmin[2]['realization']);
        $this->assertEquals(85000.0, $compDataAdmin[2]['projection']);

        // 2. Assert kabiro1 scoping limits data to Bureau 1 for all three years
        $responseKabiro = $this->actingAs($this->kabiro1)->get('/analytics');
        $responseKabiro->assertStatus(200);
        $compDataKabiro = $responseKabiro->viewData('comparisonData');

        $this->assertCount(3, $compDataKabiro);
        
        // Last Year Bureau 1
        $this->assertEquals(50000.0, $compDataKabiro[0]['budget']);
        $this->assertEquals(30000.0, $compDataKabiro[0]['realization']);
        $this->assertEquals(45000.0, $compDataKabiro[0]['projection']);

        // Current Year Bureau 1
        $this->assertEquals(60000.0, $compDataKabiro[1]['budget']);
        $this->assertEquals(15000.0, $compDataKabiro[1]['realization']);
        $this->assertEquals(50000.0, $compDataKabiro[1]['projection']);

        // Next Year Bureau 1
        $this->assertEquals(90000.0, $compDataKabiro[2]['budget']);
        $this->assertEquals(10000.0, $compDataKabiro[2]['realization']);
        $this->assertEquals(85000.0, $compDataKabiro[2]['projection']);
    }
}
