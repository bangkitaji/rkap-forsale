<?php

namespace Tests\Feature;

use Tests\TestCase;
use Livewire\Livewire;
use App\Livewire\Settings\ReportGroups;
use App\Models\User;
use App\Models\ReportGroup;
use App\Models\CoaGroup;
use App\Models\CashflowGroup;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\RoleAndUserSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\CashflowReportGroupMappingSeeder;

class ReportGroupTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $verifikatorUser;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Run role and user seeder to initialize basic roles & permissions
        $this->seed(RoleAndUserSeeder::class);

        // Fetch seeded users and assign them
        $this->adminUser = User::where('email', 'admin@kcic.co.id')->first();

        // Create verifikator user
        $roleVerifikator = Role::where('name', 'verifikator')->first();
        $this->verifikatorUser = User::create([
            'name' => 'Verifikator User',
            'email' => 'verifikator@rkap.com',
            'password' => bcrypt('password'),
        ]);
        $this->verifikatorUser->assignRole($roleVerifikator);

        // Create regular user
        $roleUser = Role::where('name', 'user')->first();
        $this->regularUser = User::create([
            'name' => 'Regular User',
            'email' => 'user@rkap.com',
            'password' => bcrypt('password'),
        ]);
        $this->regularUser->assignRole($roleUser);

        // 2. Seed MasterData (this will seed report_groups and coa_groups using JSON data)
        $this->seed(MasterDataSeeder::class);
    }

    public function test_unauthenticated_user_cannot_access_report_groups(): void
    {
        $response = $this->get('/settings/report-groups');
        $response->assertRedirect('/login');
    }

    public function test_regular_user_cannot_access_report_groups(): void
    {
        $this->actingAs($this->regularUser);

        // Since route has middleware('permission:settings.reportgroup.manage'), it should return 403 Forbidden
        $response = $this->get('/settings/report-groups');
        $response->assertStatus(403);
    }

    public function test_admin_and_verifikator_can_access_report_groups_page(): void
    {
        $this->actingAs($this->adminUser);
        $response = $this->get('/settings/report-groups');
        $response->assertStatus(200);

        $this->actingAs($this->verifikatorUser);
        $response = $this->get('/settings/report-groups');
        $response->assertStatus(200);
    }

    public function test_report_groups_livewire_renders_correctly(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(ReportGroups::class)
            ->set('perPage', 100)
            ->assertStatus(200)
            ->assertSee('Daftar Report Group')
            ->assertSee('Revenue')
            ->assertSee('Direct Cost')
            ->assertSee('Aset Lancar')
            ->assertSee('PL0001')
            ->assertSee('BS0001');
    }

    public function test_crud_create_report_group_successfully(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(ReportGroups::class)
            ->call('create')
            ->assertSet('isModalOpen', true)
            ->set('code', 'PL0099')
            ->set('type', 'PL')
            ->set('name', 'Beban Operasional Tambahan')
            ->set('description', 'Test Description')
            ->call('store')
            ->assertHasNoErrors()
            ->assertSet('isModalOpen', false);

        $this->assertDatabaseHas('report_groups', [
            'code' => 'PL0099',
            'type' => 'PL',
            'name' => 'Beban Operasional Tambahan',
            'description' => 'Test Description'
        ]);
    }

    public function test_crud_validation_enforces_required_fields_and_uniqueness(): void
    {
        $this->actingAs($this->adminUser);

        // Duplicate code verification
        Livewire::test(ReportGroups::class)
            ->call('create')
            ->set('code', 'PL0001') // already seeded in report_groups.json
            ->set('type', 'PL')
            ->set('name', 'New Duplicate Group')
            ->call('store')
            ->assertHasErrors(['code']);
    }

    public function test_crud_edit_report_group_successfully(): void
    {
        $this->actingAs($this->adminUser);

        $group = ReportGroup::where('code', 'PL0001')->first();
        $this->assertNotNull($group);

        Livewire::test(ReportGroups::class)
            ->call('edit', $group->id)
            ->assertSet('isModalOpen', true)
            ->assertSet('code', 'PL0001')
            ->assertSet('name', 'Revenue')
            ->set('name', 'Revenue Updated')
            ->call('store')
            ->assertHasNoErrors()
            ->assertSet('isModalOpen', false);

        $this->assertDatabaseHas('report_groups', [
            'id' => $group->id,
            'name' => 'Revenue Updated'
        ]);
    }

    public function test_crud_delete_fails_if_mapped_to_coa_groups(): void
    {
        $this->actingAs($this->adminUser);

        $group = ReportGroup::where('code', 'BS0001')->first(); // Aset Lancar has mapped COA Groups in seed data
        $this->assertNotNull($group);

        Livewire::test(ReportGroups::class)
            ->call('delete', $group->id)
            ->assertSee('Gagal menghapus. Report Group ini masih digunakan oleh beberapa COA Group.');

        $this->assertDatabaseHas('report_groups', [
            'id' => $group->id
        ]);
    }

    public function test_crud_delete_succeeds_if_no_mapped_coa_groups(): void
    {
        $this->actingAs($this->adminUser);

        // Create empty report group
        $group = ReportGroup::create([
            'code' => 'PL0099',
            'type' => 'PL',
            'name' => 'Empty Group',
        ]);

        Livewire::test(ReportGroups::class)
            ->call('delete', $group->id);

        $this->assertSoftDeleted('report_groups', [
            'id' => $group->id
        ]);
    }

    public function test_single_mapping_successfully(): void
    {
        $this->actingAs($this->adminUser);

        $coaGroup = CoaGroup::where('code', '1000')->first();
        $newReportGroup = ReportGroup::where('code', 'BS0002')->first(); // Aset tidak lancar

        $this->assertNotNull($coaGroup);
        $this->assertNotNull($newReportGroup);

        Livewire::test(ReportGroups::class)
            ->call('mapSingleGroup', $coaGroup->id, $newReportGroup->id);

        $this->assertEquals($newReportGroup->id, $coaGroup->fresh()->report_group_id);
    }

    public function test_bulk_mapping_successfully(): void
    {
        $this->actingAs($this->adminUser);

        $coaGroup1 = CoaGroup::where('code', '1000')->first();
        $coaGroup2 = CoaGroup::where('code', '1001')->first();
        $newReportGroup = ReportGroup::where('code', 'BS0005')->first(); // Ekuitas

        $this->assertNotNull($coaGroup1);
        $this->assertNotNull($coaGroup2);
        $this->assertNotNull($newReportGroup);

        Livewire::test(ReportGroups::class)
            ->set('selectedCoaGroups', [(string) $coaGroup1->id, (string) $coaGroup2->id])
            ->set('bulkReportGroupId', $newReportGroup->id)
            ->call('applyBulkMapping')
            ->assertHasNoErrors()
            ->assertSet('selectedCoaGroups', []);

        $this->assertEquals($newReportGroup->id, $coaGroup1->fresh()->report_group_id);
        $this->assertEquals($newReportGroup->id, $coaGroup2->fresh()->report_group_id);
    }

    public function test_cashflow_mappings_render_successfully(): void
    {
        $this->actingAs($this->adminUser);
        $this->seed(CashflowReportGroupMappingSeeder::class);

        Livewire::test(ReportGroups::class)
            ->set('activeTab', 'cashflow-mapping')
            ->assertStatus(200)
            ->assertSee('Pemetaan Cashflow Group ke Report Group')
            ->assertSee('CF0A1B')
            ->assertSee('Arus Kas Aktivitas Operasi');
    }

    public function test_single_cashflow_mapping_successfully(): void
    {
        $this->actingAs($this->adminUser);

        $cashflowGroup = CashflowGroup::create([
            'code' => 'CF_TEST_SINGLE',
            'name' => 'Test Single Cashflow',
        ]);
        $newReportGroup = ReportGroup::where('code', 'CF0002')->first(); // Arus Kas Aktivitas Investasi

        $this->assertNotNull($newReportGroup);

        Livewire::test(ReportGroups::class)
            ->call('mapSingleCashflowGroup', $cashflowGroup->id, $newReportGroup->id)
            ->assertHasNoErrors();

        $this->assertEquals($newReportGroup->id, $cashflowGroup->fresh()->report_group_id);
    }

    public function test_bulk_cashflow_mapping_successfully(): void
    {
        $this->actingAs($this->adminUser);

        $cg1 = CashflowGroup::create([
            'code' => 'CF_BULK_1',
            'name' => 'Bulk Cashflow 1',
        ]);
        $cg2 = CashflowGroup::create([
            'code' => 'CF_BULK_2',
            'name' => 'Bulk Cashflow 2',
        ]);
        $newReportGroup = ReportGroup::where('code', 'CF0003')->first(); // Arus Kas Aktivitas Pendanaan

        $this->assertNotNull($newReportGroup);

        Livewire::test(ReportGroups::class)
            ->set('selectedCashflowGroups', [(string) $cg1->id, (string) $cg2->id])
            ->set('bulkCashflowReportGroupId', $newReportGroup->id)
            ->call('applyBulkCashflowMapping')
            ->assertHasNoErrors()
            ->assertSet('selectedCashflowGroups', []);

        $this->assertEquals($newReportGroup->id, $cg1->fresh()->report_group_id);
        $this->assertEquals($newReportGroup->id, $cg2->fresh()->report_group_id);
    }
}
