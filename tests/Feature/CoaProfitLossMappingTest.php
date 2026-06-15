<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Coa;
use App\Models\CoaGroup;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Livewire\Livewire;
use App\Livewire\MasterData\CoaProfitLossMapping;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CoaProfitLossMappingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected CoaGroup $group;
    protected Coa $coa1;
    protected Coa $coa2;

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

        $this->group = CoaGroup::create([
            'code' => '500000',
            'name' => 'Biaya Operasional',
        ]);

        $this->coa1 = Coa::create([
            'coa_group_id' => $this->group->id,
            'code' => '521111',
            'title' => 'Beban Kantor',
            'description' => 'Office expense',
        ]);

        $this->coa2 = Coa::create([
            'coa_group_id' => $this->group->id,
            'code' => '521112',
            'title' => 'Beban Listrik',
            'description' => 'Electricity expense',
        ]);
    }

    public function test_component_renders_correctly_with_stats(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CoaProfitLossMapping::class)
            ->assertStatus(200)
            ->assertViewHas('stats', function ($stats) {
                return $stats['total'] === 2 &&
                       $stats['mapped'] === 0 &&
                       $stats['unmapped'] === 2;
            })
            ->assertSee('521111')
            ->assertSee('521112');
    }

    public function test_mappings_property_is_populated_from_db(): void
    {
        $this->coa1->update(['profit_loss_group' => 'revenue_passenger']);

        $component = Livewire::actingAs($this->admin)
            ->test(CoaProfitLossMapping::class);

        $mappings = $component->get('mappings');
        $this->assertEquals('revenue_passenger', $mappings[(string)$this->coa1->id]);
        $this->assertEquals('', $mappings[(string)$this->coa2->id]);
    }

    public function test_wire_model_saves_mapping_via_updated_lifecycle(): void
    {
        // Simulate what wire:model.live does: set the property value
        // Livewire automatically calls updatedMappings() when a key changes
        Livewire::actingAs($this->admin)
            ->test(CoaProfitLossMapping::class)
            ->set('mappings.' . $this->coa1->id, 'indirect_cost_admin')
            ->assertDispatched('flash-message', message: "Pemetaan COA {$this->coa1->code} berhasil diperbarui.", type: 'success');

        // Verify DB was updated
        $this->assertEquals('indirect_cost_admin', $this->coa1->fresh()->profit_loss_group);
    }

    public function test_wire_model_clears_mapping_when_empty(): void
    {
        $this->coa1->update(['profit_loss_group' => 'revenue_passenger']);

        Livewire::actingAs($this->admin)
            ->test(CoaProfitLossMapping::class)
            ->set('mappings.' . $this->coa1->id, '')
            ->assertDispatched('flash-message');

        $this->assertNull($this->coa1->fresh()->profit_loss_group);
    }

    public function test_select_all_toggles_current_page_items(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CoaProfitLossMapping::class)
            ->set('selectAll', true)
            ->assertSet('selectedCoas', [(string)$this->coa1->id, (string)$this->coa2->id])
            ->set('selectAll', false)
            ->assertSet('selectedCoas', []);
    }

    public function test_bulk_mapping_updates_selected_items(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CoaProfitLossMapping::class)
            ->set('selectedCoas', [(string)$this->coa1->id, (string)$this->coa2->id])
            ->call('bulkMap', 'direct_cost_traction')
            ->assertDispatched('flash-message', message: "Berhasil memetakan 2 COA ke kategori 'direct_cost_traction'.", type: 'success')
            ->assertSet('selectedCoas', [])
            ->assertSet('selectAll', false);

        $this->assertEquals('direct_cost_traction', $this->coa1->fresh()->profit_loss_group);
        $this->assertEquals('direct_cost_traction', $this->coa2->fresh()->profit_loss_group);
    }

    public function test_bulk_reset_clears_mapping(): void
    {
        $this->coa1->update(['profit_loss_group' => 'revenue_passenger']);
        $this->coa2->update(['profit_loss_group' => 'revenue_passenger']);

        Livewire::actingAs($this->admin)
            ->test(CoaProfitLossMapping::class)
            ->set('selectedCoas', [(string)$this->coa1->id, (string)$this->coa2->id])
            ->call('bulkMap', '__reset__')
            ->assertDispatched('flash-message', message: 'Berhasil memetakan 2 COA ke tanpa pemetaan (reset).', type: 'success')
            ->assertSet('selectedCoas', []);

        $this->assertNull($this->coa1->fresh()->profit_loss_group);
        $this->assertNull($this->coa2->fresh()->profit_loss_group);
    }

    public function test_bulk_map_does_nothing_when_empty_category_selected(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CoaProfitLossMapping::class)
            ->set('selectedCoas', [(string)$this->coa1->id])
            ->call('bulkMap', '')
            ->assertNotDispatched('flash-message');
    }

    public function test_reset_filters_clears_properties(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CoaProfitLossMapping::class)
            ->set('search', 'Listrik')
            ->set('filterCoaGroup', $this->group->id)
            ->set('filterProfitLossGroup', 'unmapped')
            ->call('resetFilters')
            ->assertSet('search', '')
            ->assertSet('filterCoaGroup', '')
            ->assertSet('filterProfitLossGroup', '');
    }
}
