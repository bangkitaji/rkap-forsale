<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\RkapPeriod;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AnalyticsRestrictedPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $verifikatorUser;
    protected User $regularUser;
    protected RkapPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup Permissions
        $permCds = Permission::firstOrCreate(['name' => 'analytics.cds.view', 'guard_name' => 'web']);
        $permOpeningBalance = Permission::firstOrCreate(['name' => 'analytics.openingbalance.manage', 'guard_name' => 'web']);
        $permBalanceSheet = Permission::firstOrCreate(['name' => 'analytics.balancesheet.view', 'guard_name' => 'web']);

        // 2. Setup Roles
        $roleAdmin = Role::firstOrCreate(['name' => 'admin']);
        $roleAdmin->givePermissionTo([$permCds, $permOpeningBalance, $permBalanceSheet]);

        $roleVerifikator = Role::firstOrCreate(['name' => 'verifikator']);
        $roleVerifikator->givePermissionTo([$permCds, $permOpeningBalance, $permBalanceSheet]);

        $roleUser = Role::firstOrCreate(['name' => 'user']);

        // 3. Setup Users
        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin-test@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->adminUser->assignRole($roleAdmin);

        $this->verifikatorUser = User::create([
            'name' => 'Verifikator User',
            'email' => 'verifikator-test@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->verifikatorUser->assignRole($roleVerifikator);

        $this->regularUser = User::create([
            'name' => 'Regular User',
            'email' => 'user-test@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->regularUser->assignRole($roleUser);

        // 4. Create Period
        $this->period = RkapPeriod::create([
            'title' => 'RKAP 2026',
            'year' => 2026,
            'status' => 'finalized',
        ]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('analytics-cds-report'))->assertRedirect('/login');
        $this->get(route('analytics-opening-balance-form'))->assertRedirect('/login');
        $this->get(route('analytics-balance-sheet'))->assertRedirect('/login');
    }

    public function test_user_without_permission_cannot_access_cds_report(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('analytics-cds-report'))
            ->assertStatus(403);
    }

    public function test_user_without_permission_cannot_access_opening_balance_form(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('analytics-opening-balance-form'))
            ->assertStatus(403);
    }

    public function test_user_without_permission_cannot_access_balance_sheet(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('analytics-balance-sheet'))
            ->assertStatus(403);
    }

    public function test_admin_can_access_all_three_analytics_pages(): void
    {
        $this->actingAs($this->adminUser)
            ->get(route('analytics-cds-report'))
            ->assertStatus(200);

        $this->actingAs($this->adminUser)
            ->get(route('analytics-opening-balance-form'))
            ->assertStatus(200);

        $this->actingAs($this->adminUser)
            ->get(route('analytics-balance-sheet'))
            ->assertStatus(200);
    }

    public function test_verifikator_can_access_all_three_analytics_pages(): void
    {
        $this->actingAs($this->verifikatorUser)
            ->get(route('analytics-cds-report'))
            ->assertStatus(200);

        $this->actingAs($this->verifikatorUser)
            ->get(route('analytics-opening-balance-form'))
            ->assertStatus(200);

        $this->actingAs($this->verifikatorUser)
            ->get(route('analytics-balance-sheet'))
            ->assertStatus(200);
    }
}
