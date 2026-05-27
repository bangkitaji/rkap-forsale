<?php

namespace Tests\Feature;

use Tests\TestCase;
use Livewire\Livewire;
use App\Livewire\Settings\Satuans;
use App\Models\Satuan;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SatuansTest extends TestCase
{
    use RefreshDatabase;

    public function test_component_renders_and_crud_works(): void
    {
        // 1. Assert component renders successfully
        Livewire::test(Satuans::class)
            ->assertStatus(200)
            ->assertSee('Reference Satuan')
            // 2. Call create to open modal in add mode
            ->call('create')
            ->assertSet('isModalOpen', true)
            ->assertSet('isEditMode', false)
            ->assertSet('name', '')
            // 3. Set properties and save
            ->set('name', 'Box')
            ->set('description', 'Satuan Box Kardus')
            ->call('store')
            ->assertHasNoErrors()
            ->assertSet('isModalOpen', false);

        // Verify it was created in the database
        $this->assertDatabaseHas('satuans', [
            'name' => 'Box',
            'description' => 'Satuan Box Kardus',
        ]);

        $satuan = Satuan::where('name', 'Box')->first();

        // 4. Test Edit mode loading
        Livewire::test(Satuans::class)
            ->call('edit', $satuan->id)
            ->assertSet('isModalOpen', true)
            ->assertSet('isEditMode', true)
            ->assertSet('satuanId', $satuan->id)
            ->assertSet('name', 'Box')
            ->assertSet('description', 'Satuan Box Kardus')
            // Modify name and save
            ->set('name', 'Box Besar')
            ->call('store')
            ->assertHasNoErrors()
            ->assertSet('isModalOpen', false);

        $this->assertDatabaseHas('satuans', [
            'id' => $satuan->id,
            'name' => 'Box Besar',
        ]);

        // 5. Test search filter
        Satuan::create(['name' => 'Meter', 'description' => 'Panjang']);

        Livewire::test(Satuans::class)
            ->set('search', 'Meter')
            ->assertSee('Meter')
            ->assertDontSee('Box Besar');

        // 6. Test delete (softdelete)
        Livewire::test(Satuans::class)
            ->call('delete', $satuan->id)
            ->assertHasNoErrors();

        // Verify it is soft-deleted
        $this->assertSoftDeleted('satuans', [
            'id' => $satuan->id,
        ]);
    }
}
