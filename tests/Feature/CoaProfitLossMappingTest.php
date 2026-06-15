<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Coa;
use App\Models\CoaGroup;
use App\Models\CoaCategory;
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
        $cat = CoaCategory::where('key', 'revenue_passenger')->first();
        $this->coa1->update(['coa_category_id' => $cat->id]);

        $component = Livewire::actingAs($this->admin)
            ->test(CoaProfitLossMapping::class);

        $mappings = $component->get('mappings');
        $this->assertEquals((string)$cat->id, $mappings[(string)$this->coa1->id]);
        $this->assertEquals('', $mappings[(string)$this->coa2->id]);
    }

    public function test_wire_model_saves_mapping_via_updated_lifecycle(): void
    {
        $cat = CoaCategory::where('key', 'indirect_cost_admin')->first();

        // Simulate what wire:model.live does: set the property value
        // Livewire automatically calls updatedMappings() when a key changes
        Livewire::actingAs($this->admin)
            ->test(CoaProfitLossMapping::class)
            ->set('mappings.' . $this->coa1->id, (string)$cat->id)
            ->assertDispatched('flash-message', message: "Pemetaan COA {$this->coa1->code} berhasil diperbarui.", type: 'success');

        // Verify DB was updated
        $this->assertEquals($cat->id, $this->coa1->fresh()->coa_category_id);
    }

    public function test_wire_model_clears_mapping_when_empty(): void
    {
        $cat = CoaCategory::where('key', 'revenue_passenger')->first();
        $this->coa1->update(['coa_category_id' => $cat->id]);

        Livewire::actingAs($this->admin)
            ->test(CoaProfitLossMapping::class)
            ->set('mappings.' . $this->coa1->id, '')
            ->assertDispatched('flash-message');

        $this->assertNull($this->coa1->fresh()->coa_category_id);
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
        $cat = CoaCategory::where('key', 'direct_cost_traction')->first();

        Livewire::actingAs($this->admin)
            ->test(CoaProfitLossMapping::class)
            ->set('selectedCoas', [(string)$this->coa1->id, (string)$this->coa2->id])
            ->call('bulkMap', (string)$cat->id)
            ->assertDispatched('flash-message', message: "Berhasil memetakan 2 COA ke {$cat->label}.", type: 'success')
            ->assertSet('selectedCoas', [])
            ->assertSet('selectAll', false);

        $this->assertEquals($cat->id, $this->coa1->fresh()->coa_category_id);
        $this->assertEquals($cat->id, $this->coa2->fresh()->coa_category_id);
    }

    public function test_bulk_reset_clears_mapping(): void
    {
        $cat = CoaCategory::where('key', 'revenue_passenger')->first();
        $this->coa1->update(['coa_category_id' => $cat->id]);
        $this->coa2->update(['coa_category_id' => $cat->id]);

        Livewire::actingAs($this->admin)
            ->test(CoaProfitLossMapping::class)
            ->set('selectedCoas', [(string)$this->coa1->id, (string)$this->coa2->id])
            ->call('bulkMap', '__reset__')
            ->assertDispatched('flash-message', message: 'Berhasil memetakan 2 COA ke tanpa pemetaan (reset).', type: 'success')
            ->assertSet('selectedCoas', []);

        $this->assertNull($this->coa1->fresh()->coa_category_id);
        $this->assertNull($this->coa2->fresh()->coa_category_id);
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
