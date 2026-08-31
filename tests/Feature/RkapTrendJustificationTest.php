<?php

namespace Tests\Feature;

use Tests\TestCase;
use Livewire\Livewire;
use App\Livewire\Rkap\RkapTrend;
use App\Models\RkapPeriod;
use App\Models\Bureau;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\User;
use App\Models\RkapSubmission;
use App\Models\RkapWorkPlan;
use App\Models\RkapBudgetItem;
use App\Models\RkapTrendJustification;
use App\Models\Activity;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RkapTrendJustificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $bureauUser;
    protected Bureau $bureau;
    protected Bureau $otherBureau;
    protected RkapPeriod $currentPeriod;
    protected RkapPeriod $proposalPeriod;
    protected RkapWorkPlan $currentWorkPlanDiscontinued;
    protected RkapWorkPlan $currentWorkPlanMatched;
    protected RkapWorkPlan $proposalWorkPlanMatched;
    protected RkapWorkPlan $proposalWorkPlanNew;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Roles & Permissions
        Permission::create(['name' => 'rkap.show']);
        $adminRole = Role::create(['name' => 'admin']);
        $adminRole->givePermissionTo('rkap.show');
        $bureauRole = Role::create(['name' => 'kepala_biro']);
        $bureauRole->givePermissionTo('rkap.show');

        // 2. Organization
        $directorate = Directorate::create([
            'code' => 'DIR01',
            'name' => 'Directorate Test',
            'is_active' => true,
        ]);

        $department = Department::create([
            'directorate_id' => $directorate->id,
            'code' => 'DEP01',
            'name' => 'Department Test',
            'is_active' => true,
        ]);

        $this->bureau = Bureau::create([
            'department_id' => $department->id,
            'code' => 'BUR01',
            'name' => 'Bureau Test',
            'is_active' => true,
        ]);

        $this->otherBureau = Bureau::create([
            'department_id' => $department->id,
            'code' => 'BUR02',
            'name' => 'Bureau Other',
            'is_active' => true,
        ]);

        // 3. Users
        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->adminUser->assignRole('admin');

        $this->bureauUser = User::create([
            'name' => 'Bureau User',
            'email' => 'bureau@example.com',
            'password' => bcrypt('password'),
            'directorate_id' => $directorate->id,
            'department_id' => $department->id,
            'bureau_id' => $this->bureau->id,
        ]);
        $this->bureauUser->assignRole('kepala_biro');

        // 4. Periods (2026 finalized/berjalan, 2027 open/usulan)
        $this->currentPeriod = RkapPeriod::create([
            'year' => 2026,
            'title' => 'RKAP 2026 Final',
            'status' => 'finalized',
            'submission_start' => now()->subYear(),
            'submission_end' => now()->subMonths(6),
        ]);

        $this->proposalPeriod = RkapPeriod::create([
            'year' => 2027,
            'title' => 'RKAP 2027 Usulan',
            'status' => 'open',
            'submission_start' => now()->subDay(),
            'submission_end' => now()->addDays(30),
        ]);

        // 5. Work Plans master & Activities
        $masterWp = \App\Models\WorkPlan::create([
            'code' => 'WP-01',
            'title' => 'Program Kerja Utama',
            'is_active' => true,
        ]);

        $act1 = Activity::create([
            'work_plan_id' => $masterWp->id,
            'code' => 'ACT-01',
            'title' => 'Kegiatan Lama Berlanjut',
            'is_active' => true,
        ]);

        $act2 = Activity::create([
            'work_plan_id' => $masterWp->id,
            'code' => 'ACT-02',
            'title' => 'Kegiatan Selesai Tidak Diusulkan',
            'is_active' => true,
        ]);

        $act3 = Activity::create([
            'work_plan_id' => $masterWp->id,
            'code' => 'ACT-03',
            'title' => 'Kegiatan Baru 2027',
            'is_active' => true,
        ]);

        // 6. Submissions
        $currentSub = RkapSubmission::create([
            'rkap_period_id' => $this->currentPeriod->id,
            'bureau_id' => $this->bureau->id,
            'created_by' => $this->bureauUser->id,
            'status' => 'approved',
            'total_budget' => 300000000,
        ]);

        $proposalSub = RkapSubmission::create([
            'rkap_period_id' => $this->proposalPeriod->id,
            'bureau_id' => $this->bureau->id,
            'created_by' => $this->bureauUser->id,
            'status' => 'submitted',
            'total_budget' => 350000000,
        ]);

        // 7. Work Plans
        // 7a. Matched in 2026
        $this->currentWorkPlanMatched = RkapWorkPlan::create([
            'rkap_submission_id' => $currentSub->id,
            'activity_id' => $act1->id,
            'program_code' => 'ACT-01',
            'program_name' => 'Kegiatan Lama Berlanjut',
        ]);
        RkapBudgetItem::create([
            'rkap_work_plan_id' => $this->currentWorkPlanMatched->id,
            'description' => 'Item Anggaran 1',
            'unit_price' => 100000000,
            'quantity' => 1,
            'total_price' => 100000000,
            'projection' => 90000000,
        ]);

        // 7b. Discontinued in 2026 (only in 2026, not in 2027)
        $this->currentWorkPlanDiscontinued = RkapWorkPlan::create([
            'rkap_submission_id' => $currentSub->id,
            'activity_id' => $act2->id,
            'program_code' => 'ACT-02',
            'program_name' => 'Kegiatan Selesai Tidak Diusulkan',
        ]);
        RkapBudgetItem::create([
            'rkap_work_plan_id' => $this->currentWorkPlanDiscontinued->id,
            'description' => 'Item Anggaran 2',
            'unit_price' => 200000000,
            'quantity' => 1,
            'total_price' => 200000000,
            'projection' => 200000000,
        ]);

        // 7c. Matched in 2027
        $this->proposalWorkPlanMatched = RkapWorkPlan::create([
            'rkap_submission_id' => $proposalSub->id,
            'activity_id' => $act1->id,
            'program_code' => 'ACT-01',
            'program_name' => 'Kegiatan Lama Berlanjut',
        ]);
        RkapBudgetItem::create([
            'rkap_work_plan_id' => $this->proposalWorkPlanMatched->id,
            'description' => 'Item Anggaran 1 (Usulan)',
            'unit_price' => 150000000,
            'quantity' => 1,
            'total_price' => 150000000,
        ]);

        // 7d. New in 2027 (only in 2027)
        $this->proposalWorkPlanNew = RkapWorkPlan::create([
            'rkap_submission_id' => $proposalSub->id,
            'activity_id' => $act3->id,
            'program_code' => 'ACT-03',
            'program_name' => 'Kegiatan Baru 2027',
        ]);
        RkapBudgetItem::create([
            'rkap_work_plan_id' => $this->proposalWorkPlanNew->id,
            'description' => 'Item Anggaran 3 (Baru)',
            'unit_price' => 200000000,
            'quantity' => 1,
            'total_price' => 200000000,
        ]);
    }

    public function test_trend_displays_all_three_activity_types(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(RkapTrend::class)
            ->assertSee('Kegiatan Lama Berlanjut')
            ->assertSee('Kegiatan Selesai Tidak Diusulkan')
            ->assertSee('Kegiatan Baru 2027')
            ->assertSee('Kegiatan Tidak Diusulkan Kembali')
            ->assertSee('Kegiatan Baru');
    }

    public function test_user_can_expand_and_save_justification_for_discontinued_activity(): void
    {
        $this->actingAs($this->bureauUser);

        $discontinuedWpId = $this->currentWorkPlanDiscontinued->id;

        Livewire::test(RkapTrend::class)
            ->call('toggleRow', $discontinuedWpId)
            ->assertSee('Justifikasi Kegiatan Tidak Diusulkan Kembali')
            ->assertSee('Alasan Tidak Diusulkan Kembali')
            ->set("justificationForm.{$discontinuedWpId}.projection", 'Proyeksi terealisasi 100%')
            ->set("justificationForm.{$discontinuedWpId}.proposal", 'Proyek telah selesai di tahun 2026 sehingga tidak perlu diusulkan kembali.')
            ->call('saveJustification', $discontinuedWpId)
            ->assertDispatched('trend-justification-saved');

        // Verify record in database
        $this->assertDatabaseHas('rkap_trend_justifications', [
            'rkap_work_plan_id' => $discontinuedWpId,
            'current_period_id' => $this->currentPeriod->id,
            'proposal_period_id' => $this->proposalPeriod->id,
            'justification_deviation_projection' => 'Proyeksi terealisasi 100%',
            'justification_deviation_proposal' => 'Proyek telah selesai di tahun 2026 sehingga tidak perlu diusulkan kembali.',
            'updated_by' => $this->bureauUser->id,
        ]);
    }

    public function test_justification_status_and_summary_counts_discontinued_activities(): void
    {
        $this->actingAs($this->adminUser);

        $discontinuedWpId = $this->currentWorkPlanDiscontinued->id;

        // Initially unfilled
        $testComponent = Livewire::test(RkapTrend::class);
        $summary = $testComponent->get('trendData')['summary'];
        $this->assertEquals(3, $summary['total_activities']); // 1 matched, 1 new, 1 discontinued
        $this->assertEquals(0, $summary['filled_count']);
        $this->assertEquals(3, $summary['unfilled_count']);

        // Fill justification for discontinued activity
        $testComponent->set("justificationForm.{$discontinuedWpId}.proposal", 'Alasan selesai')
            ->call('saveJustification', $discontinuedWpId);

        $updatedSummary = $testComponent->get('trendData')['summary'];
        $this->assertEquals(1, $updatedSummary['filled_count']);
        $this->assertEquals(2, $updatedSummary['unfilled_count']);

        // Filter status: filled
        $testComponent->set('filterStatus', 'filled')
            ->assertSee('Kegiatan Selesai Tidak Diusulkan')
            ->assertDontSee('Kegiatan Baru 2027');

        // Filter status: unfilled
        $testComponent->set('filterStatus', 'unfilled')
            ->assertDontSee('Kegiatan Selesai Tidak Diusulkan')
            ->assertSee('Kegiatan Baru 2027');
    }

    public function test_expand_all_and_collapse_all_includes_discontinued_activities(): void
    {
        $this->actingAs($this->adminUser);

        $discontinuedWpId = $this->currentWorkPlanDiscontinued->id;

        Livewire::test(RkapTrend::class)
            ->call('expandAll')
            ->assertSet('expandedRows', function ($rows) use ($discontinuedWpId) {
                return in_array($discontinuedWpId, $rows);
            })
            ->call('collapseAll')
            ->assertSet('expandedRows', []);
    }

    public function test_existing_justification_for_discontinued_activity_loads_on_mount(): void
    {
        $discontinuedWpId = $this->currentWorkPlanDiscontinued->id;

        RkapTrendJustification::create([
            'rkap_work_plan_id' => $discontinuedWpId,
            'current_period_id' => $this->currentPeriod->id,
            'proposal_period_id' => $this->proposalPeriod->id,
            'justification_deviation_projection' => 'Keterangan deviasi proyeksi ada',
            'justification_deviation_proposal' => 'Keterangan alasan tidak diusulkan kembali ada',
            'updated_by' => $this->bureauUser->id,
        ]);

        $this->actingAs($this->bureauUser);

        Livewire::test(RkapTrend::class)
            ->call('toggleRow', $discontinuedWpId)
            ->assertSet("justificationForm.{$discontinuedWpId}.projection", 'Keterangan deviasi proyeksi ada')
            ->assertSet("justificationForm.{$discontinuedWpId}.proposal", 'Keterangan alasan tidak diusulkan kembali ada');
    }

    public function test_unauthorized_bureau_user_cannot_save_justification_for_other_bureau(): void
    {
        // Create user from other bureau
        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other@example.com',
            'password' => bcrypt('password'),
            'bureau_id' => $this->otherBureau->id,
        ]);
        $otherUser->assignRole('kepala_biro');

        $this->actingAs($otherUser);

        $discontinuedWpId = $this->currentWorkPlanDiscontinued->id;

        Livewire::test(RkapTrend::class)
            ->set("justificationForm.{$discontinuedWpId}.proposal", 'Mencoba ubah tanpa hak akses')
            ->call('saveJustification', $discontinuedWpId)
            ->assertSee(__('Anda tidak memiliki hak akses untuk mengubah justifikasi biro ini.'));

        $this->assertDatabaseMissing('rkap_trend_justifications', [
            'rkap_work_plan_id' => $discontinuedWpId,
            'justification_deviation_proposal' => 'Mencoba ubah tanpa hak akses',
        ]);
    }

    public function test_new_activity_justification_status_is_complete_when_proposal_justification_is_filled(): void
    {
        $this->actingAs($this->adminUser);

        $newWpId = $this->proposalWorkPlanNew->id;

        // Fill only proposal justification for new activity
        $testComponent = Livewire::test(RkapTrend::class)
            ->set("justificationForm.{$newWpId}.proposal", 'Urgensi kegiatan baru 2027')
            ->call('saveJustification', $newWpId);

        $items = collect($testComponent->get('trendData')['items']);
        $newItem = $items->firstWhere('work_plan_id', $newWpId);

        $this->assertNotNull($newItem);
        $this->assertEquals('new', $newItem['item_type']);
        $this->assertTrue($newItem['is_filled']);
        $this->assertTrue($newItem['is_fully_filled']);

        // Check HTML renders 'Lengkap' badge
        $testComponent->assertSee('Lengkap');
    }
}
