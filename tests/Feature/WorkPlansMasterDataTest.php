<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\WorkPlan;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use App\Livewire\MasterData\WorkPlans;

class WorkPlansMasterDataTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles and permissions
        $permView = Permission::firstOrCreate(['name' => 'masterdata.workplan.view', 'guard_name' => 'web']);
        $permManage = Permission::firstOrCreate(['name' => 'masterdata.workplan.manage', 'guard_name' => 'web']);

        $roleAdmin = Role::firstOrCreate(['name' => 'admin']);
        $roleAdmin->givePermissionTo([$permView, $permManage]);

        // Create user
        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->adminUser->assignRole($roleAdmin);
    }

    public function test_admin_user_can_access_work_plans_page(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->get(route('master-data.work-plans'));
        $response->assertStatus(200);
    }

    public function test_create_sets_first_default_code_when_empty(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(WorkPlans::class)
            ->call('create')
            ->assertSet('code', '1000000001')
            ->assertSet('isEditMode', false)
            ->assertSet('isModalOpen', true);
    }

    public function test_create_auto_increments_last_code_plus_one(): void
    {
        $this->actingAs($this->adminUser);

        // Create existing work plans with numerical codes
        WorkPlan::create(['code' => '1000000001', 'title' => 'Work Plan 1']);
        WorkPlan::create(['code' => '1000000002', 'title' => 'Work Plan 2']);

        Livewire::test(WorkPlans::class)
            ->call('create')
            ->assertSet('code', '1000000003');
    }

    public function test_create_ignores_non_numerical_codes_when_auto_incrementing(): void
    {
        $this->actingAs($this->adminUser);

        // Create existing work plans with mix of numerical and non-numerical codes
        WorkPlan::create(['code' => '1000000005', 'title' => 'Work Plan 5']);
        WorkPlan::create(['code' => 'REQ-WP-ABCDEFGH', 'title' => 'Requested Work Plan']);
        WorkPlan::create(['code' => 'WP-01', 'title' => 'Another Work Plan']);

        Livewire::test(WorkPlans::class)
            ->call('create')
            ->assertSet('code', '1000000006');
    }
}
