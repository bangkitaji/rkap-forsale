<?php

namespace Tests\Feature;

use Tests\TestCase;
use Livewire\Livewire;
use App\Livewire\Settings\DifferenceGroups;
use App\Models\User;
use App\Models\DifferenceGroup;
use App\Models\Coa;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\RoleAndUserSeeder;

class DifferenceGroupsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $verifikatorUser;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Run role and user seeder to initialize basic roles & permissions
        $this->seed(RoleAndUserSeeder::class);

        // Fetch admin user
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
    }

    public function test_unauthenticated_user_cannot_access_difference_groups(): void
    {
        $response = $this->get('/settings/difference-groups');
        $response->assertRedirect('/login');
    }

    public function test_regular_user_cannot_access_difference_groups(): void
    {
        $response = $this->actingAs($this->regularUser)->get('/settings/difference-groups');
        $response->assertStatus(403);
    }

    public function test_admin_can_access_difference_groups(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/settings/difference-groups');
        $response->assertStatus(200);
        $response->assertSee('Reference Difference Group');
    }

    public function test_verifikator_can_access_difference_groups(): void
    {
        $response = $this->actingAs($this->verifikatorUser)->get('/settings/difference-groups');
        $response->assertStatus(200);
        $response->assertSee('Reference Difference Group');
    }

    public function test_crud_operations_work_successfully(): void
    {
        // 1. Test render and create modal state
        Livewire::actingAs($this->adminUser)
            ->test(DifferenceGroups::class)
            ->assertStatus(200)
            ->call('create')
            ->assertSet('isModalOpen', true)
            ->assertSet('isEditMode', false)
            ->assertSet('code', '')
            ->assertSet('name', '')
            // 2. Set properties and save
            ->set('code', 'DF100')
            ->set('name', 'Difference Item A')
            ->set('description', 'Test Description')
            ->call('store')
            ->assertHasNoErrors()
            ->assertSet('isModalOpen', false);

        // Verify database state
        $this->assertDatabaseHas('difference_groups', [
            'code' => 'DF100',
            'name' => 'Difference Item A',
            'description' => 'Test Description',
        ]);

        $group = DifferenceGroup::where('code', 'DF100')->first();

        // 3. Test Edit mode and validation ignore on current ID
        Livewire::actingAs($this->adminUser)
            ->test(DifferenceGroups::class)
            ->call('edit', $group->id)
            ->assertSet('isModalOpen', true)
            ->assertSet('isEditMode', true)
            ->assertSet('differenceGroupId', $group->id)
            ->assertSet('code', 'DF100')
            ->assertSet('name', 'Difference Item A')
            // Modify code & name, then save
            ->set('code', 'DF100-M')
            ->set('name', 'Difference Item A Modified')
            ->call('store')
            ->assertHasNoErrors()
            ->assertSet('isModalOpen', false);

        $this->assertDatabaseHas('difference_groups', [
            'id' => $group->id,
            'code' => 'DF100-M',
            'name' => 'Difference Item A Modified',
        ]);

        // 4. Test validation error when trying to save duplicate code
        DifferenceGroup::create([
            'code' => 'DF200',
            'name' => 'Another Group',
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(DifferenceGroups::class)
            ->call('edit', $group->id)
            ->set('code', 'DF200') // duplicate code
            ->call('store')
            ->assertHasErrors(['code' => 'unique']);

        // 5. Test search filter
        Livewire::actingAs($this->adminUser)
            ->test(DifferenceGroups::class)
            ->set('search', 'Another')
            ->assertSee('Another Group')
            ->assertDontSee('Difference Item A Modified');

        // 6. Test delete (softdelete)
        Livewire::actingAs($this->adminUser)
            ->test(DifferenceGroups::class)
            ->call('delete', $group->id)
            ->assertHasNoErrors();

        $this->assertSoftDeleted('difference_groups', [
            'id' => $group->id,
        ]);
    }

    public function test_tab_switching_and_mapping_works_successfully(): void
    {
        // Setup mock difference group and COAs
        $group = DifferenceGroup::create([
            'code' => 'DF999',
            'name' => 'Test Difference',
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
            ->test(DifferenceGroups::class)
            ->assertSet('activeTab', 'groups')
            ->call('switchTab', 'mapping')
            ->assertSet('activeTab', 'mapping')
            ->assertSee('Test Assets Account 1')
            ->assertSee('Test Assets Account 2')
            // 2. Test Single COA Mapping
            ->call('mapSingleCoa', $coa1->id, $group->id)
            ->assertHasNoErrors();

        $this->assertEquals($group->id, $coa1->refresh()->difference_group_id);

        // 3. Test Select All and Bulk Mapping
        $test = Livewire::actingAs($this->adminUser)
            ->test(DifferenceGroups::class)
            ->call('switchTab', 'mapping')
            ->call('toggleSelectAll', true);

        $selected = $test->get('selectedCoas');
        sort($selected);
        $expected = [(string)$coa1->id, (string)$coa2->id];
        sort($expected);
        $this->assertEquals($expected, $selected);

        $test->set('bulkDifferenceGroupId', $group->id)
            ->call('applyBulkMapping')
            ->assertHasNoErrors();

        $this->assertEquals($group->id, $coa2->refresh()->difference_group_id);

        // 4. Test Bulk Unmapping (Remove Mapping)
        Livewire::actingAs($this->adminUser)
            ->test(DifferenceGroups::class)
            ->call('switchTab', 'mapping')
            ->set('selectedCoas', [(string)$coa1->id, (string)$coa2->id])
            ->set('bulkDifferenceGroupId', '') // Unmap
            ->call('applyBulkMapping')
            ->assertHasNoErrors();

        $this->assertNull($coa1->refresh()->difference_group_id);
        $this->assertNull($coa2->refresh()->difference_group_id);
    }
}
