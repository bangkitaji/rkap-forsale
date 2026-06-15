<?php

namespace Tests\Feature;

use Tests\TestCase;
use Livewire\Livewire;
use App\Livewire\Rkap\RkapApprovalReview;
use App\Models\RkapPeriod;
use App\Models\Bureau;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\User;
use App\Models\RkapSubmission;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RkapReviewTest extends TestCase
{
    use RefreshDatabase;

    protected User $creator;
    protected User $kadept;
    protected User $direksi;
    protected User $verifikator;
    protected User $president;
    protected RkapPeriod $period;
    protected RkapSubmission $submission;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create Roles
        $roleKepalaBiro = Role::create(['name' => 'kepala_biro']);
        $roleKepalaDept = Role::create(['name' => 'kepala_departemen']);
        $roleDireksi = Role::create(['name' => 'direksi']);
        $roleVerifikator = Role::create(['name' => 'verifikator']);

        // 2. Setup organization
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

        $bureau = Bureau::create([
            'department_id' => $department->id,
            'code' => 'BUR01',
            'name' => 'Bureau Test',
            'is_active' => true,
        ]);

        // 3. Create Users
        $this->creator = User::create([
            'name' => 'Creator User',
            'email' => 'creator@example.com',
            'password' => bcrypt('password'),
            'bureau_id' => $bureau->id,
            'department_id' => $department->id,
            'directorate_id' => $directorate->id,
        ]);
        $this->creator->assignRole($roleKepalaBiro);

        $this->kadept = User::create([
            'name' => 'Kadept User',
            'email' => 'kadept@example.com',
            'password' => bcrypt('password'),
            'department_id' => $department->id,
            'directorate_id' => $directorate->id,
        ]);
        $this->kadept->assignRole($roleKepalaDept);

        $this->direksi = User::create([
            'name' => 'Direksi User',
            'email' => 'direksi@example.com',
            'password' => bcrypt('password'),
            'directorate_id' => $directorate->id,
        ]);
        $this->direksi->assignRole($roleDireksi);

        $this->verifikator = User::create([
            'name' => 'Verifikator User',
            'email' => 'verifikator@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->verifikator->assignRole($roleVerifikator);

        $rolePresident = Role::create(['name' => 'president_director']);
        $this->president = User::create([
            'name' => 'President User',
            'email' => 'president@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->president->assignRole($rolePresident);

        // 4. Period & Submission
        $this->period = RkapPeriod::create([
            'year' => 2026,
            'title' => 'RKAP 2026',
            'status' => 'open',
            'submission_start' => now()->subDay(),
            'submission_end' => now()->addDay(),
        ]);

        $this->submission = RkapSubmission::create([
            'rkap_period_id' => $this->period->id,
            'bureau_id' => $bureau->id,
            'created_by' => $this->creator->id,
            'status' => 'submitted', // Initially submitted, waiting for Kadep
            'total_budget' => 50000,
        ]);
    }

    public function test_kadept_can_see_approval_buttons_in_submitted_status(): void
    {
        $this->actingAs($this->kadept);

        Livewire::test(RkapApprovalReview::class, ['id' => $this->submission->id])
            ->assertStatus(200)
            ->assertSee('Setujui RKAP')
            ->assertSee('Minta Revisi');
    }

    public function test_direksi_cannot_see_approval_buttons_in_submitted_status(): void
    {
        $this->actingAs($this->direksi);

        Livewire::test(RkapApprovalReview::class, ['id' => $this->submission->id])
            ->assertStatus(200)
            ->assertDontSee('Setujui RKAP')
            ->assertDontSee('Minta Revisi');
    }

    public function test_direksi_can_see_approval_buttons_in_dir_review_status(): void
    {
        // Update submission to dir_review
        $this->submission->update(['status' => 'dir_review']);

        $this->actingAs($this->direksi);

        Livewire::test(RkapApprovalReview::class, ['id' => $this->submission->id])
            ->assertStatus(200)
            ->assertSee('Setujui RKAP')
            ->assertSee('Minta Revisi');
    }

    public function test_verifikator_can_see_approval_buttons_in_final_review_status(): void
    {
        // Update submission to final_review
        $this->submission->update(['status' => 'final_review']);

        $this->actingAs($this->verifikator);

        Livewire::test(RkapApprovalReview::class, ['id' => $this->submission->id])
            ->assertStatus(200)
            ->assertSee('Setujui RKAP')
            ->assertSee('Minta Revisi');
    }

    public function test_verifikator_approving_transitions_to_pdir_review(): void
    {
        $this->submission->update(['status' => 'final_review']);

        $this->actingAs($this->verifikator);

        Livewire::test(RkapApprovalReview::class, ['id' => $this->submission->id])
            ->call('approve');

        $this->assertEquals('pdir_review', $this->submission->fresh()->status);
    }

    public function test_president_director_can_see_approval_buttons_in_pdir_review_status(): void
    {
        $this->submission->update(['status' => 'pdir_review']);

        $this->actingAs($this->president);

        Livewire::test(RkapApprovalReview::class, ['id' => $this->submission->id])
            ->assertStatus(200)
            ->assertSee('Setujui RKAP')
            ->assertSee('Minta Revisi');
    }

    public function test_president_director_approving_transitions_to_approved(): void
    {
        $this->submission->update(['status' => 'pdir_review']);

        $this->actingAs($this->president);

        Livewire::test(RkapApprovalReview::class, ['id' => $this->submission->id])
            ->call('approve');

        $this->assertEquals('approved', $this->submission->fresh()->status);
    }

    public function test_president_director_requesting_revision_transitions_to_draft(): void
    {
        $this->submission->update(['status' => 'pdir_review']);

        $this->actingAs($this->president);

        Livewire::test(RkapApprovalReview::class, ['id' => $this->submission->id])
            ->set('revisionReason', 'Need more details on training expenses.')
            ->call('requestRevision');

        $this->assertEquals('draft', $this->submission->fresh()->status);

        // Ensure approval log has the revision reason and role
        $latestApproval = $this->submission->approvals()->first();
        $this->assertEquals('president_director', $latestApproval->role);
        $this->assertEquals('revision_requested', $latestApproval->action);
        $this->assertEquals('Need more details on training expenses.', $latestApproval->comments);
    }
}
