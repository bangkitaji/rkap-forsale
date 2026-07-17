<?php

namespace Tests\Feature;

use Tests\TestCase;
use Livewire\Livewire;
use App\Livewire\Rkap\RkapSubmissionList;
use App\Models\RkapPeriod;
use App\Models\Bureau;
use App\Models\User;
use App\Models\RkapSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RkapSubmissionListTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected RkapPeriod $openPeriod;
    protected RkapPeriod $openPeriodWithDraft;
    protected RkapPeriod $openPeriodWithSubmitted;
    protected Bureau $bureau;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup organization
        $directorate = \App\Models\Directorate::create([
            'code' => 'DIR01',
            'name' => 'Directorate Test',
            'is_active' => true,
        ]);

        $department = \App\Models\Department::create([
            'directorate_id' => $directorate->id,
            'code' => 'DEP01',
            'name' => 'Department Test',
            'is_active' => true,
        ]);

        $this->bureau = Bureau::create([
            'department_id' => $department->id,
            'code' => 'BUR01',
            'name' => 'Bureau Test',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'bureau_id' => $this->bureau->id,
        ]);

        // Setup RKAP Periods
        $this->openPeriod = RkapPeriod::create([
            'year' => 2026,
            'title' => 'RKAP 2026 Open',
            'status' => 'open',
            'submission_start' => now()->subDay(),
            'submission_end' => now()->addDay(),
        ]);

        $this->openPeriodWithDraft = RkapPeriod::create([
            'year' => 2027,
            'title' => 'RKAP 2027 Open with Draft',
            'status' => 'open',
            'submission_start' => now()->subDay(),
            'submission_end' => now()->addDay(),
        ]);

        $this->openPeriodWithSubmitted = RkapPeriod::create([
            'year' => 2028,
            'title' => 'RKAP 2028 Open with Submitted',
            'status' => 'open',
            'submission_start' => now()->subDay(),
            'submission_end' => now()->addDay(),
        ]);

        // Create submissions
        // 1. Draft submission for openPeriodWithDraft
        RkapSubmission::create([
            'rkap_period_id' => $this->openPeriodWithDraft->id,
            'bureau_id' => $this->bureau->id,
            'created_by' => $this->user->id,
            'status' => 'draft',
            'total_budget' => 0,
        ]);

        // 2. Submitted (non-draft) submission for openPeriodWithSubmitted
        RkapSubmission::create([
            'rkap_period_id' => $this->openPeriodWithSubmitted->id,
            'bureau_id' => $this->bureau->id,
            'created_by' => $this->user->id,
            'status' => 'submitted',
            'total_budget' => 0,
        ]);
    }

    public function test_rkap_submission_list_renders_period_selector_correctly(): void
    {
        $this->actingAs($this->user);

        Livewire::test(RkapSubmissionList::class)
            ->set('showPeriodSelector', true)
            ->assertSee('RKAP 2026 Open')
            ->assertSee('RKAP 2027 Open with Draft')
            ->assertSee('RKAP 2028 Open with Submitted')
            ->assertSee('Upload Massal')
            ->assertSee('Edit Draft')
            ->assertSee('Sudah Diinput');
    }
}
