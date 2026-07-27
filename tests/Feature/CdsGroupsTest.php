<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\CdsGroup;
use Spatie\Permission\Models\Role;
use Livewire\Livewire;
use App\Livewire\Settings\CdsGroups;

class CdsGroupsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleAndUserSeeder::class);
    }

    public function test_guest_cannot_access_cds_groups_page()
    {
        $response = $this->get(route('settings-cds-groups'));
        $response->assertRedirect(route('login'));
    }

    public function test_user_without_permission_cannot_access_cds_groups_page()
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $response = $this->actingAs($user)->get(route('settings-cds-groups'));
        $response->assertStatus(403);
    }

    public function test_admin_can_access_cds_groups_page()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get(route('settings-cds-groups'));
        $response->assertStatus(200);
        $response->assertSee('CDS Group');
    }
    
    public function test_verifikator_can_access_cds_groups_page()
    {
        $verifikator = User::factory()->create();
        $verifikator->assignRole('verifikator');

        $response = $this->actingAs($verifikator)->get(route('settings-cds-groups'));
        $response->assertStatus(200);
    }

    public function test_admin_can_create_cds_group()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(CdsGroups::class)
            ->call('create')
            ->set('code', 'CDS_TEST_01')
            ->set('name', 'Test CDS Group')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cds_groups', [
            'code' => 'CDS_TEST_01',
            'name' => 'Test CDS Group',
        ]);
    }

    public function test_admin_can_update_cds_group()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $cdsGroup = CdsGroup::create([
            'code' => 'CDS_OLD',
            'name' => 'Old Name',
        ]);

        Livewire::actingAs($admin)
            ->test(CdsGroups::class)
            ->call('edit', $cdsGroup->id)
            ->set('name', 'Updated Name')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cds_groups', [
            'id' => $cdsGroup->id,
            'code' => 'CDS_OLD',
            'name' => 'Updated Name',
        ]);
    }

    public function test_admin_can_delete_cds_group()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $cdsGroup = CdsGroup::create([
            'code' => 'CDS_DELETE',
            'name' => 'To Delete',
        ]);

        Livewire::actingAs($admin)
            ->test(CdsGroups::class)
            ->call('delete', $cdsGroup->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('cds_groups', [
            'id' => $cdsGroup->id,
        ]);
    }

    public function test_cds_group_code_must_be_unique()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        CdsGroup::create([
            'code' => 'CDS_UNIQUE',
            'name' => 'Existing',
        ]);

        Livewire::actingAs($admin)
            ->test(CdsGroups::class)
            ->call('create')
            ->set('code', 'CDS_UNIQUE')
            ->set('name', 'New Group')
            ->call('save')
            ->assertHasErrors(['code' => 'unique']);
    }
}
