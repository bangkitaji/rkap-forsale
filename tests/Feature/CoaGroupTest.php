<?php

namespace Tests\Feature;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\CoaGroup;
use App\Models\Coa;
use App\Livewire\MasterData\CoaGroups;
use App\Livewire\MasterData\Coas;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CoaGroupTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the COA Groups Livewire component renders and basic CRUD works.
     */
    public function test_coa_groups_crud_works(): void
    {
        // 1. Assert component renders successfully
        Livewire::test(CoaGroups::class)
            ->assertStatus(200)
            ->assertSee('Manage Groups')
            ->assertSee('COA Mapping')
            // 2. Call create to open modal in add mode
            ->call('createGroup')
            ->assertSet('isModalOpen', true)
            ->assertSet('isEditMode', false)
            ->assertSet('groupCode', '')
            // 3. Set properties and save
            ->set('groupCode', 'OPEX')
            ->set('groupName', 'Operational Expenditure')
            ->set('groupDescription', 'Operational cost mapping group')
            ->call('storeGroup')
            ->assertHasNoErrors()
            ->assertSet('isModalOpen', false);

        // Verify group is created in DB
        $this->assertDatabaseHas('coa_groups', [
            'code' => 'OPEX',
            'name' => 'Operational Expenditure',
            'description' => 'Operational cost mapping group',
        ]);

        $group = CoaGroup::where('code', 'OPEX')->firstOrFail();

        // 4. Test Edit mode loading
        Livewire::test(CoaGroups::class)
            ->call('editGroup', $group->id)
            ->assertSet('isModalOpen', true)
            ->assertSet('isEditMode', true)
            ->assertSet('groupId', $group->id)
            ->assertSet('groupCode', 'OPEX')
            ->assertSet('groupName', 'Operational Expenditure')
            // Modify name and save
            ->set('groupName', 'OPEX Modified')
            ->call('storeGroup')
            ->assertHasNoErrors()
            ->assertSet('isModalOpen', false);

        $this->assertDatabaseHas('coa_groups', [
            'id' => $group->id,
            'name' => 'OPEX Modified',
        ]);

        // 5. Test search filter
        CoaGroup::create([
            'code' => 'CAPEX',
            'name' => 'Capital Expenditure',
        ]);

        Livewire::test(CoaGroups::class)
            ->set('searchGroup', 'CAPEX')
            ->assertSee('Capital Expenditure')
            ->assertDontSee('OPEX Modified');

        // 6. Test delete (softdelete)
        Livewire::test(CoaGroups::class)
            ->call('deleteGroup', $group->id)
            ->assertHasNoErrors();

        // Verify it is soft-deleted
        $this->assertSoftDeleted('coa_groups', [
            'id' => $group->id,
        ]);
    }

    /**
     * Test mapping multiple COAs to a COA Group.
     */
    public function test_bulk_mapping_and_unmapping(): void
    {
        // Create a COA Group
        $group = CoaGroup::create([
            'code' => 'REV',
            'name' => 'Revenue Group',
        ]);

        // Create some COAs
        $coa1 = Coa::create([
            'code' => '411001',
            'title' => 'Product Sales',
        ]);
        $coa2 = Coa::create([
            'code' => '411002',
            'title' => 'Service Sales',
        ]);

        // 1. Bulk Map COAs to the Group
        Livewire::test(CoaGroups::class)
            ->set('activeTab', 'mapping')
            ->set('selectedCoas', [$coa1->id, $coa2->id])
            ->set('targetGroupId', $group->id)
            ->call('mapSelected')
            ->assertHasNoErrors()
            ->assertSet('selectedCoas', []); // Selection should clear on success

        // Assert database updated
        $this->assertDatabaseHas('coas', [
            'id' => $coa1->id,
            'coa_group_id' => $group->id,
        ]);
        $this->assertDatabaseHas('coas', [
            'id' => $coa2->id,
            'coa_group_id' => $group->id,
        ]);

        // 2. Bulk Unmap COAs
        Livewire::test(CoaGroups::class)
            ->set('activeTab', 'mapping')
            ->set('selectedCoas', [$coa1->id])
            ->call('unmapSelected')
            ->assertHasNoErrors();

        // Assert mapping removed
        $this->assertDatabaseHas('coas', [
            'id' => $coa1->id,
            'coa_group_id' => null,
        ]);
        // Other coa remains mapped
        $this->assertDatabaseHas('coas', [
            'id' => $coa2->id,
            'coa_group_id' => $group->id,
        ]);
    }

    /**
     * Test inline COA Group assignment in COAs CRUD component.
     */
    public function test_inline_coa_group_assignment(): void
    {
        // Create group
        $group = CoaGroup::create([
            'code' => 'EQUITY',
            'name' => 'Equity Group',
        ]);

        // Test creating a COA with a group assigned
        Livewire::test(Coas::class)
            ->call('create')
            ->set('code', '310001')
            ->set('title', 'Common Stock')
            ->set('coaGroupId', $group->id)
            ->call('store')
            ->assertHasNoErrors();

        // Verify in DB
        $this->assertDatabaseHas('coas', [
            'code' => '310001',
            'coa_group_id' => $group->id,
        ]);

        $coa = Coa::where('code', '310001')->firstOrFail();

        // Test editing a COA and changing its group
        Livewire::test(Coas::class)
            ->call('edit', $coa->id)
            ->assertSet('coaGroupId', $group->id)
            ->set('coaGroupId', '') // Unmap
            ->call('store')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('coas', [
            'id' => $coa->id,
            'coa_group_id' => null,
        ]);
    }
}
