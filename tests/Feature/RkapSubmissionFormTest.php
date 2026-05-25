<?php

namespace Tests\Feature;

use Tests\TestCase;
use Livewire\Livewire;
use App\Livewire\Rkap\RkapSubmissionForm;
use App\Models\RkapPeriod;
use App\Models\WorkPlan;
use App\Models\Activity;
use App\Models\Coa;
use App\Models\Bureau;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RkapSubmissionFormTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected RkapPeriod $period;
    protected WorkPlan $workPlan;
    protected Activity $activityWithCoas;
    protected Activity $activityWithoutCoas;
    protected Coa $coa1;
    protected Coa $coa2;

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

        $bureau = Bureau::create([
            'department_id' => $department->id,
            'code' => 'BUR01',
            'name' => 'Bureau Test',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'bureau_id' => $bureau->id,
        ]);

        // Setup RKAP Period
        $this->period = RkapPeriod::create([
            'year' => 2026,
            'title' => 'RKAP 2026',
            'status' => 'open',
            'submission_start' => now()->subDay(),
            'submission_end' => now()->addDay(),
        ]);

        // Setup WorkPlan
        $this->workPlan = WorkPlan::create([
            'code' => 'WP001',
            'title' => 'Test Work Plan',
        ]);

        // Setup Activities
        $this->activityWithCoas = Activity::create([
            'work_plan_id' => $this->workPlan->id,
            'code' => 'ACT001',
            'title' => 'Activity with COAs',
        ]);

        $this->activityWithoutCoas = Activity::create([
            'work_plan_id' => $this->workPlan->id,
            'code' => 'ACT002',
            'title' => 'Activity without COAs',
        ]);

        // Setup COAs
        $this->coa1 = Coa::create([
            'code' => '510101',
            'title' => 'Gaji Karyawan',
        ]);

        $this->coa2 = Coa::create([
            'code' => '510102',
            'title' => 'Tunjangan Karyawan',
        ]);

        // Map COAs to activityWithCoas
        $this->activityWithCoas->coas()->attach([$this->coa1->id, $this->coa2->id]);
    }

    public function test_rkap_submission_form_page_renders_successfully(): void
    {
        $this->actingAs($this->user);

        $response = $this->get(route('rkap-submissions-create', $this->period->id));
        $response->assertStatus(200);
        $response->assertSee('Buat RKAP');
    }

    public function test_budget_items_are_populated_when_activity_with_coas_is_selected(): void
    {
        $this->actingAs($this->user);

        Livewire::test(RkapSubmissionForm::class, ['periodId' => $this->period->id])
            ->assertSet('workPlans.0.work_plan_id', null)
            ->assertSet('workPlans.0.activity_id', null)
            // First workPlan has 1 default empty budget item
            ->assertCount('workPlans.0.budget_items', 1)
            ->assertSet('workPlans.0.budget_items.0.coa_id', null)

            // Set WorkPlan
            ->set('workPlans.0.work_plan_id', $this->workPlan->id)
            // Set Activity with COAs
            ->set('workPlans.0.activity_id', $this->activityWithCoas->id)

            // Should now show 2 budget items corresponding to the mapped COAs
            ->assertCount('workPlans.0.budget_items', 2)
            ->assertSet('workPlans.0.budget_items.0.coa_id', $this->coa1->id)
            ->assertSet('workPlans.0.budget_items.0.account_code', $this->coa1->code)
            ->assertSet('workPlans.0.budget_items.0.description', $this->coa1->title)
            ->assertSet('workPlans.0.budget_items.1.coa_id', $this->coa2->id)
            ->assertSet('workPlans.0.budget_items.1.account_code', $this->coa2->code)
            ->assertSet('workPlans.0.budget_items.1.description', $this->coa2->title);
    }

    public function test_budget_items_show_one_empty_item_when_activity_without_coas_is_selected(): void
    {
        $this->actingAs($this->user);

        Livewire::test(RkapSubmissionForm::class, ['periodId' => $this->period->id])
            ->set('workPlans.0.work_plan_id', $this->workPlan->id)
            // Set Activity with COAs first to populate budget items
            ->set('workPlans.0.activity_id', $this->activityWithCoas->id)
            ->assertCount('workPlans.0.budget_items', 2)

            // Switch to Activity without COAs
            ->set('workPlans.0.activity_id', $this->activityWithoutCoas->id)
            // Should reset to 1 empty budget item
            ->assertCount('workPlans.0.budget_items', 1)
            ->assertSet('workPlans.0.budget_items.0.coa_id', null)
            ->assertSet('workPlans.0.budget_items.0.account_code', '')
            ->assertSet('workPlans.0.budget_items.0.description', '');
    }

    public function test_budget_items_reset_to_one_empty_item_when_activity_is_cleared(): void
    {
        $this->actingAs($this->user);

        Livewire::test(RkapSubmissionForm::class, ['periodId' => $this->period->id])
            ->set('workPlans.0.work_plan_id', $this->workPlan->id)
            // Set Activity with COAs to populate budget items
            ->set('workPlans.0.activity_id', $this->activityWithCoas->id)
            ->assertCount('workPlans.0.budget_items', 2)

            // Clear Activity
            ->set('workPlans.0.activity_id', null)
            // Should reset to 1 empty budget item
            ->assertCount('workPlans.0.budget_items', 1)
            ->assertSet('workPlans.0.budget_items.0.coa_id', null);
    }

    public function test_budget_items_reset_to_one_empty_item_when_work_plan_is_changed(): void
    {
        $this->actingAs($this->user);

        Livewire::test(RkapSubmissionForm::class, ['periodId' => $this->period->id])
            ->set('workPlans.0.work_plan_id', $this->workPlan->id)
            // Set Activity with COAs to populate budget items
            ->set('workPlans.0.activity_id', $this->activityWithCoas->id)
            ->assertCount('workPlans.0.budget_items', 2)

            // Change WorkPlan ID to something else or clear it
            ->set('workPlans.0.work_plan_id', null)
            // Should clear activity and reset budget items to 1 empty item
            ->assertSet('workPlans.0.activity_id', null)
            ->assertCount('workPlans.0.budget_items', 1)
            ->assertSet('workPlans.0.budget_items.0.coa_id', null);
    }

    public function test_cannot_save_budget_item_coa_not_mapped_to_selected_activity(): void
    {
        $this->actingAs($this->user);

        // Activity without COAs -> should not allow COA selection
        $component = Livewire::test(RkapSubmissionForm::class, ['periodId' => $this->period->id])
            ->set('workPlans.0.work_plan_id', $this->workPlan->id)
            ->set('workPlans.0.activity_id', $this->activityWithoutCoas->id)
            ->set('workPlans.0.budget_items.0.coa_id', $this->coa1->id);

        $component->call('saveDraft')
            ->assertHasErrors(); // mapping validation should prevent saving
    }

    public function test_coa_options_are_filtered_by_activity(): void
    {
        $this->actingAs($this->user);

        $component = Livewire::test(RkapSubmissionForm::class, ['periodId' => $this->period->id]);

        // When no activity is selected, getCoaOptionsForIndex should return all COAs
        $options = $component->instance()->getCoaOptionsForIndex(0);
        $this->assertCount(2, $options); // coa1 and coa2 exist

        // Select activity with coas
        $component->set('workPlans.0.work_plan_id', $this->workPlan->id)
            ->set('workPlans.0.activity_id', $this->activityWithCoas->id);

        $options = $component->instance()->getCoaOptionsForIndex(0);
        $this->assertCount(2, $options);
        $this->assertTrue($options->contains($this->coa1));
        $this->assertTrue($options->contains($this->coa2));

        // Select activity without coas
        $component->set('workPlans.0.activity_id', $this->activityWithoutCoas->id);
        $options = $component->instance()->getCoaOptionsForIndex(0);
        // Fallback to all COAs
        $this->assertCount(2, $options);
    }
}
