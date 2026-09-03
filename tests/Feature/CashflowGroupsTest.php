<?php

namespace Tests\Feature;

use Tests\TestCase;
use Livewire\Livewire;
use App\Livewire\Settings\CashflowGroups;
use App\Models\User;
use App\Models\CashflowGroup;
use App\Models\Coa;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\RoleAndUserSeeder;

class CashflowGroupsTest extends TestCase
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
        $this->adminUser = User::where('email', config('rkap.admin_email', 'admin@rkap.com'))->first() ?? User::first();

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
    }

    public function test_unauthenticated_user_cannot_access_cashflow_groups(): void
    {
        $response = $this->get('/settings/cashflow-groups');
        $response->assertRedirect('/login');
    }

    public function test_regular_user_cannot_access_cashflow_groups(): void
    {
        $response = $this->actingAs($this->regularUser)->get('/settings/cashflow-groups');
        $response->assertStatus(403);
    }

    public function test_admin_can_access_cashflow_groups(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/settings/cashflow-groups');
        $response->assertStatus(200);
        $response->assertSee('Reference Cashflow Group');
    }

    public function test_verifikator_can_access_cashflow_groups(): void
    {
        $response = $this->actingAs($this->verifikatorUser)->get('/settings/cashflow-groups');
        $response->assertStatus(200);
        $response->assertSee('Reference Cashflow Group');
    }

    public function test_crud_operations_work_successfully(): void
    {
        // 1. Test render and create modal state
        Livewire::actingAs($this->adminUser)
            ->test(CashflowGroups::class)
            ->assertStatus(200)
            ->call('create')
            ->assertSet('isModalOpen', true)
            ->assertSet('isEditMode', false)
            ->assertSet('code', '')
            ->assertSet('name', '')
            // 2. Set properties and save
            ->set('code', 'CF100')
            ->set('name', 'Farebox Receipt')
            ->set('description', 'Farebox Revenue group')
            ->call('store')
            ->assertHasNoErrors()
            ->assertSet('isModalOpen', false);

        // Verify database state
        $this->assertDatabaseHas('cashflow_groups', [
            'code' => 'CF100',
            'name' => 'Farebox Receipt',
            'description' => 'Farebox Revenue group',
        ]);

        $group = CashflowGroup::where('code', 'CF100')->first();

        // 3. Test Edit mode and validation ignore on current ID
        Livewire::actingAs($this->adminUser)
            ->test(CashflowGroups::class)
            ->call('edit', $group->id)
            ->assertSet('isModalOpen', true)
            ->assertSet('isEditMode', true)
            ->assertSet('cashflowGroupId', $group->id)
            ->assertSet('code', 'CF100')
            ->assertSet('name', 'Farebox Receipt')
            // Modify code & name, then save
            ->set('code', 'CF100-M')
            ->set('name', 'Farebox Receipt Modified')
            ->call('store')
            ->assertHasNoErrors()
            ->assertSet('isModalOpen', false);

        $this->assertDatabaseHas('cashflow_groups', [
            'id' => $group->id,
            'code' => 'CF100-M',
            'name' => 'Farebox Receipt Modified',
        ]);

        // 4. Test validation error when trying to save duplicate code
        CashflowGroup::create([
            'code' => 'CF200',
            'name' => 'Another Group',
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(CashflowGroups::class)
            ->call('edit', $group->id)
            ->set('code', 'CF200') // duplicate code
            ->call('store')
            ->assertHasErrors(['code' => 'unique']);

        // 5. Test search filter
        Livewire::actingAs($this->adminUser)
            ->test(CashflowGroups::class)
            ->set('search', 'Another')
            ->assertSee('Another Group')
            ->assertDontSee('Farebox Receipt Modified');

        // 6. Test delete (softdelete)
        Livewire::actingAs($this->adminUser)
            ->test(CashflowGroups::class)
            ->call('delete', $group->id)
            ->assertHasNoErrors();

        $this->assertSoftDeleted('cashflow_groups', [
            'id' => $group->id,
        ]);
    }

    public function test_tab_switching_and_mapping_works_successfully(): void
    {
        // Setup mock cashflow group and COAs
        $group = CashflowGroup::create([
            'code' => 'CF999',
            'name' => 'Test Cashflow',
        ]);

        $coa1 = Coa::create([
            'code' => '1000000001',
            'title' => 'Test Assets Account 1',
        ]);

        $coa2 = Coa::create([
            'code' => '1000000002',
            'title' => 'Test Assets Account 2',
        ]);

        // 1. Test Tab Switching
        Livewire::actingAs($this->adminUser)
            ->test(CashflowGroups::class)
            ->assertSet('activeTab', 'groups')
            ->call('switchTab', 'mapping')
            ->assertSet('activeTab', 'mapping')
            ->assertSee('Test Assets Account 1')
            ->assertSee('Test Assets Account 2')
            // 2. Test Single COA Mapping
            ->call('mapSingleCoa', $coa1->id, $group->id)
            ->assertHasNoErrors();

        $this->assertEquals($group->id, $coa1->refresh()->cashflow_group_id);

        // 3. Test Select All and Bulk Mapping
        $test = Livewire::actingAs($this->adminUser)
            ->test(CashflowGroups::class)
            ->call('switchTab', 'mapping')
            ->call('toggleSelectAll', true);

        $selected = $test->get('selectedCoas');
        sort($selected);
        $expected = [(string)$coa1->id, (string)$coa2->id];
        sort($expected);
        $this->assertEquals($expected, $selected);

        $test->set('bulkCashflowGroupId', $group->id)
            ->call('applyBulkMapping')
            ->assertHasNoErrors();

        $this->assertEquals($group->id, $coa2->refresh()->cashflow_group_id);

        // 4. Test Bulk Unmapping (Remove Mapping)
        Livewire::actingAs($this->adminUser)
            ->test(CashflowGroups::class)
            ->call('switchTab', 'mapping')
            ->set('selectedCoas', [(string)$coa1->id, (string)$coa2->id])
            ->set('bulkCashflowGroupId', '') // Unmap
            ->call('applyBulkMapping')
            ->assertHasNoErrors();

        $this->assertNull($coa1->refresh()->cashflow_group_id);
        $this->assertNull($coa2->refresh()->cashflow_group_id);
    }
}
