<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Bureau;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\WorkPlan;
use App\Models\Activity;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use App\Livewire\Rkap\RkapRequests;

class RkapRequestsTest extends TestCase
{
    use RefreshDatabase;

    private User $bureauUser;
    private User $adminUser;
    private User $verifikatorUser;
    private Bureau $bureau;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles and permissions
        $permRkapShow = Permission::firstOrCreate(['name' => 'rkap.show', 'guard_name' => 'web']);
        $permApprove = Permission::firstOrCreate(['name' => 'masterdata.request.approve', 'guard_name' => 'web']);

        $roleUser = Role::firstOrCreate(['name' => 'user']);
        $roleUser->givePermissionTo($permRkapShow);

        $roleAdmin = Role::firstOrCreate(['name' => 'admin']);
        $roleAdmin->givePermissionTo([$permRkapShow, $permApprove]);

        $roleVerifikator = Role::firstOrCreate(['name' => 'verifikator']);
        $roleVerifikator->givePermissionTo([$permRkapShow, $permApprove]);

        // Setup organization
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

        // Create bureau
        $this->bureau = Bureau::create([
            'department_id' => $department->id,
            'code' => 'BUR01',
            'name' => 'Bureau Test',
            'is_active' => true,
        ]);

        // Create users
        $this->bureauUser = User::create([
            'name' => 'Bureau User',
            'email' => 'bureau@example.com',
            'password' => bcrypt('password'),
            'bureau_id' => $this->bureau->id,
        ]);
        $this->bureauUser->assignRole($roleUser);

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->adminUser->assignRole($roleAdmin);

        $this->verifikatorUser = User::create([
            'name' => 'Verifikator User',
            'email' => 'verifikator@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->verifikatorUser->assignRole($roleVerifikator);
    }

    public function test_bureau_user_can_access_requests_page(): void
    {
        $this->actingAs($this->bureauUser);

        $response = $this->get(route('rkap-requests'));
        $response->assertStatus(200);
    }

    public function test_bureau_user_can_request_new_work_plan(): void
    {
        $this->actingAs($this->bureauUser);

        Livewire::test(RkapRequests::class)
            ->set('requestType', 'work_plan')
            ->set('wpTitle', 'Test Work Plan 99')
            ->call('submitRequest');

        $this->assertDatabaseHas('work_plans', [
            'title' => 'Test Work Plan 99',
            'approval_status' => 'pending',
            'requested_by_bureau_id' => $this->bureau->id,
        ]);

        $created = WorkPlan::where('title', 'Test Work Plan 99')->first();
        $this->assertNotNull($created);
        $this->assertStringStartsWith('REQ-WP-', $created->code);
    }

    public function test_bureau_user_can_request_new_activity(): void
    {
        $this->actingAs($this->bureauUser);

        // Pre-create an approved work plan
        $wp = WorkPlan::create([
            'code' => 'WP.01',
            'title' => 'Approved Work Plan',
            'approval_status' => 'approved',
        ]);

        Livewire::test(RkapRequests::class)
            ->set('requestType', 'activity')
            ->set('actWorkPlanId', $wp->id)
            ->set('actTitle', 'Test Activity 99')
            ->set('actDescription', 'Activity Description')
            ->call('submitRequest');

        $this->assertDatabaseHas('activities', [
            'work_plan_id' => $wp->id,
            'title' => 'Test Activity 99',
            'description' => 'Activity Description',
            'approval_status' => 'pending',
            'requested_by_bureau_id' => $this->bureau->id,
        ]);

        $created = Activity::where('title', 'Test Activity 99')->first();
        $this->assertNotNull($created);
        $this->assertStringStartsWith('REQ-ACT-', $created->code);
    }

    public function test_admin_can_approve_requests(): void
    {
        $wp = WorkPlan::create([
            'code' => 'REQ-WP-PENDING',
            'title' => 'Pending Work Plan',
            'approval_status' => 'pending',
            'requested_by_bureau_id' => $this->bureau->id,
        ]);

        $this->actingAs($this->adminUser);

        Livewire::test(RkapRequests::class)
            ->call('openApproveModal', 'work_plan', $wp->id)
            ->set('approvalCode', 'WP.APPROVED.CODE')
            ->call('confirmApprove');

        $this->assertEquals('approved', $wp->fresh()->approval_status);
        $this->assertEquals('WP.APPROVED.CODE', $wp->fresh()->code);
    }

    public function test_verifikator_can_reject_requests(): void
    {
        $wp = WorkPlan::create([
            'code' => 'REQ-WP-PENDING',
            'title' => 'Pending Work Plan',
            'approval_status' => 'pending',
            'requested_by_bureau_id' => $this->bureau->id,
        ]);

        $this->actingAs($this->verifikatorUser);

        Livewire::test(RkapRequests::class)
            ->call('openRejectModal', 'work_plan', $wp->id)
            ->set('rejectionNote', 'Rejection reason notes')
            ->call('confirmReject');

        $this->assertEquals('rejected', $wp->fresh()->approval_status);
    }

    public function test_non_approver_cannot_approve_or_reject(): void
    {
        $wp = WorkPlan::create([
            'code' => 'REQ-WP-PENDING',
            'title' => 'Pending Work Plan',
            'approval_status' => 'pending',
            'requested_by_bureau_id' => $this->bureau->id,
        ]);

        $this->actingAs($this->bureauUser);

        Livewire::test(RkapRequests::class)
            ->call('openApproveModal', 'work_plan', $wp->id)
            ->assertStatus(403);

        Livewire::test(RkapRequests::class)
            ->call('openRejectModal', 'work_plan', $wp->id)
            ->assertStatus(403);
    }

    public function test_approval_modal_auto_fills_next_code(): void
    {
        // Create an approved work plan with numeric code
        WorkPlan::create([
            'code' => '1000000050',
            'title' => 'Some Approved Work Plan',
            'approval_status' => 'approved',
        ]);

        // Create a pending work plan request
        $pendingWp = WorkPlan::create([
            'code' => 'REQ-WP-PENDING',
            'title' => 'Pending Work Plan',
            'approval_status' => 'pending',
            'requested_by_bureau_id' => $this->bureau->id,
        ]);

        $this->actingAs($this->adminUser);

        Livewire::test(RkapRequests::class)
            ->call('openApproveModal', 'work_plan', $pendingWp->id)
            ->assertSet('approvalCode', '1000000051');
    }
}
