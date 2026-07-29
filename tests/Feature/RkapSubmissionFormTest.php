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

  public function test_budget_items_section_is_hidden_by_default_and_shown_after_selection(): void
  {
    $this->actingAs($this->user);

    $t = Livewire::test(RkapSubmissionForm::class, ['periodId' => $this->period->id]);
    // Initially, since work plan and activity are null, it should show the notice
    $t->assertSee('Silakan pilih');
    $t->assertSee('detail anggaran belanja');
    $t->assertDontSee('Uraian');
    $t->assertDontSee('Tambah Item Belanja');

    // Set WorkPlan (still no activity selected, should still show notice)
    $t->set('workPlans.0.work_plan_id', $this->workPlan->id);
    $t->assertSee('Silakan pilih');
    $t->assertSee('detail anggaran belanja');
    $t->assertDontSee('Uraian');

    // Set Activity with COAs (both are set now, so notice should be gone and budget items table/button shown)
    $t->set('workPlans.0.activities.0.activity_id', $this->activityWithCoas->id);
    $t->assertDontSee('detail anggaran belanja');
    $t->assertSee('Uraian');
    $t->assertSee('Tambah Item Belanja');
  }

  public function test_budget_items_are_populated_when_activity_with_coas_is_selected(): void
  {
    $this->actingAs($this->user);

    Livewire::test(RkapSubmissionForm::class, ['periodId' => $this->period->id])
      ->assertSet('workPlans.0.work_plan_id', null)
      ->assertSet('workPlans.0.activities.0.activity_id', null)
      // First workPlan first activity has 1 default empty budget item
      ->assertCount('workPlans.0.activities.0.budget_items', 1)
      ->assertSet('workPlans.0.activities.0.budget_items.0.coa_id', null)

      // Set WorkPlan
      ->set('workPlans.0.work_plan_id', $this->workPlan->id)
      // Set Activity with COAs
      ->set('workPlans.0.activities.0.activity_id', $this->activityWithCoas->id)

      // Should now show 2 budget items corresponding to the mapped COAs
      ->assertCount('workPlans.0.activities.0.budget_items', 2)
      ->assertSet('workPlans.0.activities.0.budget_items.0.coa_id', $this->coa1->id)
      ->assertSet('workPlans.0.activities.0.budget_items.0.account_code', $this->coa1->code)
      ->assertSet('workPlans.0.activities.0.budget_items.0.description', $this->coa1->title)
      ->assertSet('workPlans.0.activities.0.budget_items.1.coa_id', $this->coa2->id)
      ->assertSet('workPlans.0.activities.0.budget_items.1.account_code', $this->coa2->code)
      ->assertSet('workPlans.0.activities.0.budget_items.1.description', $this->coa2->title);
  }

  public function test_budget_items_show_one_empty_item_when_activity_without_coas_is_selected(): void
  {
    $this->actingAs($this->user);

    Livewire::test(RkapSubmissionForm::class, ['periodId' => $this->period->id])
      ->set('workPlans.0.work_plan_id', $this->workPlan->id)
      // Set Activity with COAs first to populate budget items
      ->set('workPlans.0.activities.0.activity_id', $this->activityWithCoas->id)
      ->assertCount('workPlans.0.activities.0.budget_items', 2)

      // Switch to Activity without COAs
      ->set('workPlans.0.activities.0.activity_id', $this->activityWithoutCoas->id)
      // Should reset to 1 empty budget item
      ->assertCount('workPlans.0.activities.0.budget_items', 1)
      ->assertSet('workPlans.0.activities.0.budget_items.0.coa_id', null)
      ->assertSet('workPlans.0.activities.0.budget_items.0.account_code', '')
      ->assertSet('workPlans.0.activities.0.budget_items.0.description', '');
  }

  public function test_budget_items_reset_to_one_empty_item_when_activity_is_cleared(): void
  {
    $this->actingAs($this->user);

    Livewire::test(RkapSubmissionForm::class, ['periodId' => $this->period->id])
      ->set('workPlans.0.work_plan_id', $this->workPlan->id)
      // Set Activity with COAs to populate budget items
      ->set('workPlans.0.activities.0.activity_id', $this->activityWithCoas->id)
      ->assertCount('workPlans.0.activities.0.budget_items', 2)

      // Clear Activity
      ->set('workPlans.0.activities.0.activity_id', null)
      // Should reset to 1 empty budget item
      ->assertCount('workPlans.0.activities.0.budget_items', 1)
      ->assertSet('workPlans.0.activities.0.budget_items.0.coa_id', null);
  }

  public function test_budget_items_reset_to_one_empty_item_when_work_plan_is_changed(): void
  {
    $this->actingAs($this->user);

    Livewire::test(RkapSubmissionForm::class, ['periodId' => $this->period->id])
      ->set('workPlans.0.work_plan_id', $this->workPlan->id)
      // Set Activity with COAs to populate budget items
      ->set('workPlans.0.activities.0.activity_id', $this->activityWithCoas->id)
      ->assertCount('workPlans.0.activities.0.budget_items', 2)

      // Change WorkPlan ID to something else or clear it
      ->set('workPlans.0.work_plan_id', null)
      // Should clear activity and reset budget items to 1 empty item
      ->assertSet('workPlans.0.activities.0.activity_id', null)
      ->assertCount('workPlans.0.activities.0.budget_items', 1)
      ->assertSet('workPlans.0.activities.0.budget_items.0.coa_id', null);
  }

  public function test_can_save_budget_item_coa_not_mapped_to_selected_activity(): void
  {
    $this->actingAs($this->user);

    // Activity without COAs -> should forbid COA selection and throw validation errors
    $component = Livewire::test(RkapSubmissionForm::class, ['periodId' => $this->period->id])
      ->set('workPlans.0.work_plan_id', $this->workPlan->id)
      ->set('workPlans.0.activities.0.activity_id', $this->activityWithoutCoas->id)
      ->set('workPlans.0.activities.0.budget_items.0.coa_id', $this->coa1->id);

    $component->call('saveDraft')
      ->assertHasErrors(['workPlans.0.activities.0.activity_id']);
  }

  public function test_cannot_save_budget_item_coa_without_activity(): void
  {
    $this->actingAs($this->user);

    // COA selected but Activity is null -> should throw validation errors
    $component = Livewire::test(RkapSubmissionForm::class, ['periodId' => $this->period->id])
      ->set('workPlans.0.work_plan_id', $this->workPlan->id)
      ->set('workPlans.0.activities.0.activity_id', null)
      ->set('workPlans.0.activities.0.budget_items.0.coa_id', $this->coa1->id);

    $component->call('saveDraft')
      ->assertHasErrors();
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
      ->set('workPlans.0.activities.0.activity_id', $this->activityWithCoas->id);

    $options = $component->instance()->getCoaOptionsForIndex(0);
    $this->assertCount(2, $options);
    $this->assertTrue($options->contains($this->coa1));
    $this->assertTrue($options->contains($this->coa2));

    // Select activity without coas
    $component->set('workPlans.0.activities.0.activity_id', $this->activityWithoutCoas->id);
    $options = $component->instance()->getCoaOptionsForIndex(0);
    // Fallback to all COAs
    $this->assertCount(2, $options);
  }

  public function test_can_edit_and_save_existing_submission_draft(): void
  {
    $this->actingAs($this->user);

    // Create an existing submission
    $submission = \App\Models\RkapSubmission::create([
      'rkap_period_id' => $this->period->id,
      'bureau_id' => $this->user->bureau_id,
      'created_by' => $this->user->id,
      'status' => 'draft',
      'notes' => 'Initial notes',
    ]);

    $wp = \App\Models\RkapWorkPlan::create([
      'rkap_submission_id' => $submission->id,
      'work_plan_id' => $this->workPlan->id,
      'activity_id' => $this->activityWithCoas->id,
      'program_name' => $this->workPlan->title,
      'quantity' => 1,
      'sort_order' => 0,
    ]);

    \App\Models\RkapBudgetItem::create([
      'rkap_work_plan_id' => $wp->id,
      'account_code' => $this->coa1->code,
      'description' => $this->coa1->title,
      'quantity' => 1,
      'unit_price' => 1000,
    ]);

    // Load submission form in edit mode
    Livewire::test(RkapSubmissionForm::class, ['id' => $submission->id])
      ->assertSet('notes', 'Initial notes')
      ->set('notes', 'Updated notes')
      ->call('saveDraft')
      ->assertHasNoErrors();

    $this->assertDatabaseHas('rkap_submissions', [
      'id' => $submission->id,
      'notes' => 'Updated notes',
      'status' => 'draft', // status must remain 'draft'
    ]);
  }

  public function test_forbids_new_submission_if_already_exists_for_same_period(): void
  {
    $this->actingAs($this->user);

    // Create an existing submission for this period
    \App\Models\RkapSubmission::create([
      'rkap_period_id' => $this->period->id,
      'bureau_id' => $this->user->bureau_id,
      'created_by' => $this->user->id,
      'status' => 'draft',
    ]);

    // Attempting to load the form to create a new submission in the same period
    $response = $this->get(route('rkap-submissions-create', $this->period->id));

    // It should redirect to list with error flash message
    $response->assertRedirect(route('rkap-submissions'));
    $response->assertSessionHas('error', 'Biro Anda sudah membuat pengajuan RKAP untuk periode ini.');
  }

  public function test_version_history_diff_and_rendering(): void
  {
    $this->actingAs($this->user);

    // Create submission
    $submission = \App\Models\RkapSubmission::create([
      'rkap_period_id' => $this->period->id,
      'bureau_id' => $this->user->bureau_id,
      'created_by' => $this->user->id,
      'status' => 'submitted',
    ]);

    $wp = \App\Models\RkapWorkPlan::create([
      'rkap_submission_id' => $submission->id,
      'work_plan_id' => $this->workPlan->id,
      'activity_id' => $this->activityWithCoas->id,
      'program_name' => $this->workPlan->title,
      'quantity' => 1,
      'sort_order' => 0,
    ]);

    \App\Models\RkapBudgetItem::create([
      'rkap_work_plan_id' => $wp->id,
      'account_code' => $this->coa1->code,
      'description' => $this->coa1->title,
      'quantity' => 2,
      'unit_price' => 5000,
    ]);

    // Submit to create initial version
    $submission->submit();

    // Increment version to create a second version
    $submission->revise();
    $submission->submit();

    // Test version history component
    Livewire::test(\App\Livewire\Rkap\RkapVersionHistory::class, ['id' => $submission->id])
      ->assertStatus(200)
      ->call('compareToPrevious')
      ->assertHasNoErrors();
  }

  public function test_version_history_diff_with_duplicate_budget_item_keys(): void
  {
    $this->actingAs($this->user);

    // Create submission
    $submission = \App\Models\RkapSubmission::create([
      'rkap_period_id' => $this->period->id,
      'bureau_id' => $this->user->bureau_id,
      'created_by' => $this->user->id,
      'status' => 'submitted',
    ]);

    $wp = \App\Models\RkapWorkPlan::create([
      'rkap_submission_id' => $submission->id,
      'work_plan_id' => $this->workPlan->id,
      'activity_id' => $this->activityWithCoas->id,
      'program_name' => $this->workPlan->title,
      'quantity' => 1,
      'sort_order' => 0,
    ]);

    // Version 1 has 2 identical duplicate items (same coa and description)
    $bi1 = \App\Models\RkapBudgetItem::create([
      'rkap_work_plan_id' => $wp->id,
      'account_code' => $this->coa1->code,
      'description' => $this->coa1->title,
      'quantity' => 2,
      'unit_price' => 5000,
    ]);

    $bi2 = \App\Models\RkapBudgetItem::create([
      'rkap_work_plan_id' => $wp->id,
      'account_code' => $this->coa1->code,
      'description' => $this->coa1->title,
      'quantity' => 3,
      'unit_price' => 4000,
    ]);

    // Submit to create initial version (Version 1)
    $submission->submit();

    // Start a revision (Version 2)
    $submission->revise();

    // Delete the second duplicate item
    $bi2->delete();

    // Modify the first duplicate item (quantity 2 -> 5)
    $bi1->update(['quantity' => 5]);

    // Add a new duplicate item (different quantity/price)
    \App\Models\RkapBudgetItem::create([
      'rkap_work_plan_id' => $wp->id,
      'account_code' => $this->coa1->code,
      'description' => $this->coa1->title,
      'quantity' => 1,
      'unit_price' => 10000,
    ]);

    // Submit version 2
    $submission->submit();

    // Load the version history component
    $testComponent = Livewire::test(\App\Livewire\Rkap\RkapVersionHistory::class, ['id' => $submission->id])
      ->assertStatus(200)
      ->call('compareToPrevious');

    $diff = $testComponent->instance()->diff;

    $this->assertNotEmpty($diff);

    // Find the modified work plan diff item
    $wpDiff = collect($diff)->firstWhere('item.program_name', $this->workPlan->title);
    $this->assertNotNull($wpDiff);
    $this->assertEquals('modified', $wpDiff['status']);

    $biDiffs = $wpDiff['budget_items_diff'];
    $this->assertNotEmpty($biDiffs);

    // We expect:
    // - 1 modified item (bi1: quantity changed from 2 to 5)
    // - 1 added item (new item: quantity 1, price 10000)
    // - 1 removed item (bi2: deleted)

    $modifiedCount = collect($biDiffs)->where('status', 'modified')->count();
    $addedCount = collect($biDiffs)->where('status', 'added')->count();
    $removedCount = collect($biDiffs)->where('status', 'removed')->count();

    $this->assertEquals(1, $modifiedCount);
    $this->assertEquals(1, $addedCount);
    $this->assertEquals(1, $removedCount);
  }

  public function test_can_duplicate_budget_item_under_same_coa(): void
  {
    $this->actingAs($this->user);

    Livewire::test(RkapSubmissionForm::class, ['periodId' => $this->period->id])
      ->set('workPlans.0.work_plan_id', $this->workPlan->id)
      ->set('workPlans.0.activities.0.activity_id', $this->activityWithCoas->id)
      // Assert that there are initially 2 budget items
      ->assertCount('workPlans.0.activities.0.budget_items', 2)
      ->assertSet('workPlans.0.activities.0.budget_items.0.coa_id', $this->coa1->id)

      // Duplicate the first budget item (index 0)
      ->call('duplicateBudgetItem', 0, 0, 0)

      // Assert that we now have 3 budget items
      ->assertCount('workPlans.0.activities.0.budget_items', 3)

      // Assert the duplicated item is placed right next to it (index 1) with same COA parameters
      ->assertSet('workPlans.0.activities.0.budget_items.1.coa_id', $this->coa1->id)
      ->assertSet('workPlans.0.activities.0.budget_items.1.account_code', $this->coa1->code)
      ->assertSet('workPlans.0.activities.0.budget_items.1.description', $this->coa1->title)

      // But quantity and unit price should be empty/default
      ->assertSet('workPlans.0.activities.0.budget_items.1.quantity', 1)
      ->assertSet('workPlans.0.activities.0.budget_items.1.unit_price', 0);
  }

  public function test_budget_item_unit_price_leading_zeros_and_empty_clear_are_sanitized(): void
  {
    $this->actingAs($this->user);

    Livewire::test(RkapSubmissionForm::class, ['periodId' => $this->period->id])
      ->set('workPlans.0.work_plan_id', $this->workPlan->id)
      ->set('workPlans.0.activities.0.activity_id', $this->activityWithCoas->id)

      // Leading zeros must be stripped
      ->set('workPlans.0.activities.0.budget_items.0.unit_price', '05000')
      ->assertSet('workPlans.0.activities.0.budget_items.0.unit_price', 5000)

      // Lone zero stays 0
      ->set('workPlans.0.activities.0.budget_items.0.unit_price', '0')
      ->assertSet('workPlans.0.activities.0.budget_items.0.unit_price', 0)

      // Multiple zeros collapse to 0
      ->set('workPlans.0.activities.0.budget_items.0.unit_price', '00')
      ->assertSet('workPlans.0.activities.0.budget_items.0.unit_price', 0)

      // Empty/null is reset to 0 (prevents validation error)
      ->set('workPlans.0.activities.0.budget_items.0.unit_price', '')
      ->assertSet('workPlans.0.activities.0.budget_items.0.unit_price', 0)

      ->set('workPlans.0.activities.0.budget_items.0.unit_price', null)
      ->assertSet('workPlans.0.activities.0.budget_items.0.unit_price', 0);
  }

  public function test_can_select_and_deselect_all_months(): void
  {
    $this->actingAs($this->user);

    Livewire::test(RkapSubmissionForm::class, ['periodId' => $this->period->id])
      ->set('workPlans.0.work_plan_id', $this->workPlan->id)
      ->set('workPlans.0.activities.0.activity_id', $this->activityWithCoas->id)

      // Initially no months selected
      ->assertSet('workPlans.0.activities.0.budget_items.0.distribution_months', [])
      ->assertSet('workPlans.0.activities.0.budget_items.0.cash_out_months', [])

      // Select all months
      ->call('selectAllMonths', 0, 0, 0)
      ->assertCount('workPlans.0.activities.0.budget_items.0.distribution_months', 12)

      // Select all cash out months
      ->call('selectAllCashOutMonths', 0, 0, 0)
      ->assertCount('workPlans.0.activities.0.budget_items.0.cash_out_months', 12)

      // Deselect all months (toggles off when already 12 are selected)
      ->call('selectAllMonths', 0, 0, 0)
      ->assertCount('workPlans.0.activities.0.budget_items.0.distribution_months', 0)

      // Deselect all cash out months
      ->call('selectAllCashOutMonths', 0, 0, 0)
      ->assertCount('workPlans.0.activities.0.budget_items.0.cash_out_months', 0);
  }

  public function test_clearing_coa_clears_budget_item_details(): void
  {
    $this->actingAs($this->user);

    Livewire::test(RkapSubmissionForm::class, ['periodId' => $this->period->id])
      ->set('workPlans.0.work_plan_id', $this->workPlan->id)
      ->set('workPlans.0.activities.0.activity_id', $this->activityWithCoas->id)

      // Populate some details
      ->set('workPlans.0.activities.0.budget_items.0.unit', 'Bh')
      ->set('workPlans.0.activities.0.budget_items.0.quantity', 5)
      ->set('workPlans.0.activities.0.budget_items.0.unit_price', 10000)
      ->set('workPlans.0.activities.0.budget_items.0.remarks', 'Sewa printer')
      ->call('selectAllMonths', 0, 0, 0)
      ->call('selectAllCashOutMonths', 0, 0, 0)

      // Assert values are set
      ->assertSet('workPlans.0.activities.0.budget_items.0.unit', 'Bh')
      ->assertSet('workPlans.0.activities.0.budget_items.0.quantity', 5)
      ->assertSet('workPlans.0.activities.0.budget_items.0.unit_price', 10000)
      ->assertSet('workPlans.0.activities.0.budget_items.0.remarks', 'Sewa printer')
      ->assertCount('workPlans.0.activities.0.budget_items.0.distribution_months', 12)
      ->assertCount('workPlans.0.activities.0.budget_items.0.cash_out_months', 12)

      // Clear the COA
      ->call('updateGroupCoa', 0, 0, 0, null)

      // Assert the COA fields are cleared
      ->assertSet('workPlans.0.activities.0.budget_items.0.coa_id', null)
      ->assertSet('workPlans.0.activities.0.budget_items.0.account_code', '')
      ->assertSet('workPlans.0.activities.0.budget_items.0.description', '')

      // Assert detail belanja child fields are cleared/reset
      ->assertSet('workPlans.0.activities.0.budget_items.0.unit', '')
      ->assertSet('workPlans.0.activities.0.budget_items.0.quantity', 1)
      ->assertSet('workPlans.0.activities.0.budget_items.0.unit_price', 0)
      ->assertSet('workPlans.0.activities.0.budget_items.0.remarks', '')
      ->assertCount('workPlans.0.activities.0.budget_items.0.distribution_months', 0)
      ->assertCount('workPlans.0.activities.0.budget_items.0.cash_out_months', 0);
  }

  public function test_activities_can_be_retrieved_without_work_plan_selected(): void
  {
    $this->actingAs($this->user);

    $component = Livewire::test(RkapSubmissionForm::class, ['periodId' => $this->period->id]);

    // When work_plan_id is null, it should return all activities
    $component->assertSet('workPlans.0.work_plan_id', null);
    $activities = $component->instance()->getActivitiesForIndex(0);

    $this->assertCount(2, $activities);
    $this->assertTrue($activities->contains($this->activityWithCoas));
    $this->assertTrue($activities->contains($this->activityWithoutCoas));
  }

  public function test_work_plan_is_automatically_selected_when_activity_is_selected(): void
  {
    $this->actingAs($this->user);

    Livewire::test(RkapSubmissionForm::class, ['periodId' => $this->period->id])
      ->assertSet('workPlans.0.work_plan_id', null)
      ->assertSet('workPlans.0.activities.0.activity_id', null)

      // Set activity
      ->set('workPlans.0.activities.0.activity_id', $this->activityWithCoas->id)

      // Assert that the work plan was auto-populated
      ->assertSet('workPlans.0.work_plan_id', $this->workPlan->id);
  }

  public function test_work_plan_options_are_filtered_when_activity_is_selected(): void
  {
    $this->actingAs($this->user);

    // Create a second work plan for comparison
    $secondWorkPlan = WorkPlan::create([
      'code' => 'WP002',
      'title' => 'Second Work Plan',
    ]);

    $component = Livewire::test(RkapSubmissionForm::class, ['periodId' => $this->period->id]);

    // When no activity is selected, should return all work plans
    $options = $component->instance()->getWorkPlanOptionsForIndex(0);
    $this->assertCount(2, $options);
    $this->assertTrue($options->contains($this->workPlan));
    $this->assertTrue($options->contains($secondWorkPlan));

    // When an activity is selected, should return only its mapped work plan
    $component->set('workPlans.0.activities.0.activity_id', $this->activityWithCoas->id);
    $options = $component->instance()->getWorkPlanOptionsForIndex(0);

    $this->assertCount(1, $options);
    $this->assertTrue($options->contains($this->workPlan));
    $this->assertFalse($options->contains($secondWorkPlan));
  }

  public function test_second_unit_and_volume_math_and_validation(): void
  {
    $this->actingAs($this->user);

    // Instantiate component and set up a budget item
    $component = Livewire::test(RkapSubmissionForm::class, ['periodId' => $this->period->id])
      ->set('workPlans.0.work_plan_id', $this->workPlan->id)
      ->set('workPlans.0.activities.0.activity_id', $this->activityWithCoas->id)
      // Item 1: Vol 1 = 2, Harga = 50000, no Satuan 2
      ->set('workPlans.0.activities.0.budget_items.0.quantity', 2)
      ->set('workPlans.0.activities.0.budget_items.0.unit_price', 50000)
      ->set('workPlans.0.activities.0.budget_items.0.unit_2', '')
      ->set('workPlans.0.activities.0.budget_items.0.quantity_2', null);

    // Grand total should be 2 * 50000 = 100000
    $this->assertEquals(100000, $component->get('grandTotal'));

    // Now set Satuan 2 to "Box" and Vol 2 to 3
    $component->set('workPlans.0.activities.0.budget_items.0.unit_2', 'Box')
      ->set('workPlans.0.activities.0.budget_items.0.quantity_2', 3);

    // Grand total should update to 2 * 3 * 50000 = 300000
    $this->assertEquals(300000, $component->get('grandTotal'));

    // Distribute evenly over 3 months
    $component->call('toggleMonth', 0, 0, 0, 1) // Jan
      ->call('toggleMonth', 0, 0, 0, 2) // Feb
      ->call('toggleMonth', 0, 0, 0, 3) // Mar
      ->call('distributeEvenly', 0, 0, 0);

    $distribution = $component->get('workPlans.0.activities.0.budget_items.0.monthly_distribution');
    $this->assertEquals(100000, $distribution[1]);
    $this->assertEquals(100000, $distribution[2]);
    $this->assertEquals(100000, $distribution[3]);

    // Validation test: quantity_2 = -1 should fail
    $component->set('workPlans.0.activities.0.budget_items.0.quantity_2', -1)
      ->call('saveDraft')
      ->assertHasErrors(['workPlans.0.activities.0.budget_items.0.quantity_2']);

    // Set valid volume 2 again and save draft
    $component->set('workPlans.0.activities.0.budget_items.0.quantity_2', 3)
      ->call('saveDraft')
      ->assertHasNoErrors();

    // Check database contents
    $this->assertDatabaseHas('rkap_budget_items', [
      'account_code' => $this->coa1->code,
      'unit_2' => 'Box',
      'quantity_2' => 3,
      'total_price' => 300000.00,
    ]);
  }

  public function test_deleting_budget_item_quantity_or_price_does_not_throw_operand_error(): void
  {
    $this->actingAs($this->user);

    // Instantiate component and set up a budget item
    Livewire::test(RkapSubmissionForm::class, ['periodId' => $this->period->id])
      ->set('workPlans.0.work_plan_id', $this->workPlan->id)
      ->set('workPlans.0.activities.0.activity_id', $this->activityWithCoas->id)

      // Set quantity to empty string (simulating deletion by user)
      ->set('workPlans.0.activities.0.budget_items.0.quantity', '')

      // Set unit_price to empty string
      ->set('workPlans.0.activities.0.budget_items.0.unit_price', '')

      // Set quantity_2 to empty string
      ->set('workPlans.0.activities.0.budget_items.0.quantity_2', '')

      // Ensure no operand type error was thrown and it renders successfully
      ->assertStatus(200);
  }

  public function test_past_period_payment_functionality_and_validation(): void
  {
    $this->actingAs($this->user);

    // Create a past period
    $pastPeriod = RkapPeriod::create([
      'year' => 2025,
      'title' => 'RKAP 2025',
      'status' => 'closed',
      'submission_start' => now()->subYear(),
      'submission_end' => now()->subYear()->addMonth(),
    ]);

    // Create a liability COA (starts with 2)
    $liabilityCoa = Coa::create([
      'code' => '210101',
      'title' => 'Hutang Usaha',
    ]);

    // 1. Toggle checkbox should reset past_period_id and budget_items
    Livewire::test(RkapSubmissionForm::class, ['periodId' => $this->period->id])
      ->set('workPlans.0.work_plan_id', $this->workPlan->id)
      ->set('workPlans.0.activities.0.activity_id', $this->activityWithCoas->id)
      ->set('workPlans.0.activities.0.past_period_id', $pastPeriod->id)
      ->set('workPlans.0.activities.0.is_past_period_payment', true)
      ->assertSet('workPlans.0.activities.0.past_period_id', null)
      ->assertCount('workPlans.0.activities.0.budget_items', 1);

    // 2. COA filtering: when is_past_period_payment is true, only COAs starting with 2 are returned
    $test = Livewire::test(RkapSubmissionForm::class, ['periodId' => $this->period->id])
      ->set('workPlans.0.work_plan_id', $this->workPlan->id)
      ->set('workPlans.0.activities.0.is_past_period_payment', true);

    $filteredCoas = $test->instance()->getCoaOptionsForIndex(0, 0);
    $this->assertTrue($filteredCoas->contains('id', $liabilityCoa->id));
    $this->assertFalse($filteredCoas->contains('id', $this->coa1->id));

    // 3. Validation: is_past_period_payment = true requires past_period_id
    Livewire::test(RkapSubmissionForm::class, ['periodId' => $this->period->id])
      ->set('workPlans.0.work_plan_id', $this->workPlan->id)
      ->set('workPlans.0.activities.0.activity_id', $this->activityWithCoas->id)
      ->set('workPlans.0.activities.0.is_past_period_payment', true)
      ->set('workPlans.0.activities.0.past_period_id', null)
      ->set('workPlans.0.activities.0.budget_items.0.coa_id', $liabilityCoa->id)
      ->call('saveDraft')
      ->assertHasErrors(['workPlans.0.activities.0.past_period_id']);

    // 4. Validation: is_past_period_payment = true fails if selected COA doesn't start with '2'
    Livewire::test(RkapSubmissionForm::class, ['periodId' => $this->period->id])
      ->set('workPlans.0.work_plan_id', $this->workPlan->id)
      ->set('workPlans.0.activities.0.activity_id', $this->activityWithCoas->id)
      ->set('workPlans.0.activities.0.is_past_period_payment', true)
      ->set('workPlans.0.activities.0.past_period_id', $pastPeriod->id)
      ->set('workPlans.0.activities.0.budget_items.0.coa_id', $this->coa1->id) // Starts with 5
      ->call('saveDraft')
      ->assertHasErrors(['workPlans.0.activities.0.budget_items.0.coa_id']);
  }

  public function test_decimal_quantities_support(): void
  {
    $this->actingAs($this->user);

    // Instantiate component and set up a budget item
    $component = Livewire::test(RkapSubmissionForm::class, ['periodId' => $this->period->id])
      ->set('workPlans.0.work_plan_id', $this->workPlan->id)
      ->set('workPlans.0.activities.0.activity_id', $this->activityWithCoas->id)
      // Set decimal quantity Vol 1 = 1.5, Vol 2 = 2.5, unit_price = 10000
      ->set('workPlans.0.activities.0.budget_items.0.quantity', 1.5)
      ->set('workPlans.0.activities.0.budget_items.0.unit_price', 10000)
      ->set('workPlans.0.activities.0.budget_items.0.unit_2', 'Box')
      ->set('workPlans.0.activities.0.budget_items.0.quantity_2', 2.5);

    // Grand total should be 1.5 * 2.5 * 10000 = 37500
    $this->assertEquals(37500, $component->get('grandTotal'));

    // Distribute evenly over 2 months
    $component->call('toggleMonth', 0, 0, 0, 1) // Jan
      ->call('toggleMonth', 0, 0, 0, 2) // Feb
      ->call('distributeEvenly', 0, 0, 0);

    $distribution = $component->get('workPlans.0.activities.0.budget_items.0.monthly_distribution');
    $this->assertEquals(18750, $distribution[1]);
    $this->assertEquals(18750, $distribution[2]);

    // Save draft and make sure no errors
    $component->call('saveDraft')
      ->assertHasNoErrors();

    // Check database contents to ensure they are stored correctly as decimals
    $this->assertDatabaseHas('rkap_budget_items', [
      'account_code' => $this->coa1->code,
      'quantity' => 1.5,
      'quantity_2' => 2.5,
      'total_price' => 37500.00,
    ]);
  }

  public function test_revenue_coa_cash_out_can_exceed_budget_on_submission_form(): void
  {
    $this->actingAs($this->user);

    // 1. Create a revenue COA and map it to a new activity
    $revenueCoa = Coa::create([
      'code' => '410102',
      'title' => 'Pendapatan Tiket Cadangan',
    ]);

    $activityWithSingleCoa = Activity::create([
      'work_plan_id' => $this->workPlan->id,
      'code' => 'ACT003',
      'title' => 'Activity with Single COA',
    ]);
    $activityWithSingleCoa->coas()->attach($revenueCoa->id);

    // 2. Test component
    $component = Livewire::test(RkapSubmissionForm::class, ['periodId' => $this->period->id])
      ->set('workPlans.0.work_plan_id', $this->workPlan->id)
      ->set('workPlans.0.activities.0.activity_id', $activityWithSingleCoa->id)
      ->set('workPlans.0.activities.0.budget_items.0.quantity', 2)
      ->set('workPlans.0.activities.0.budget_items.0.unit_price', 5000); // total 10000

    // Distribute monthly budget (must equal total item: 10000)
    $component->call('toggleMonth', 0, 0, 0, 1)
      ->set('workPlans.0.activities.0.budget_items.0.monthly_distribution.1', 10000);

    // Distribute cash out: exceeding total budget (allocated 15000)
    $component->call('toggleCashOutMonth', 0, 0, 0, 1)
      ->set('workPlans.0.activities.0.budget_items.0.cash_out_distribution.1', 15000);

    // Try to submit for review, should not throw error for cash out exceeding budget
    $component->call('submitForReview')
      ->assertHasNoErrors();
  }

  public function test_expense_coa_cash_out_exceed_budget_fails_on_submission_form(): void
  {
    $this->actingAs($this->user);

    $activityWithOnlyCoa1 = Activity::create([
      'work_plan_id' => $this->workPlan->id,
      'code' => 'ACT004',
      'title' => 'Activity with Only Coa1',
    ]);
    $activityWithOnlyCoa1->coas()->attach($this->coa1->id);

    $component = Livewire::test(RkapSubmissionForm::class, ['periodId' => $this->period->id])
      ->set('workPlans.0.work_plan_id', $this->workPlan->id)
      ->set('workPlans.0.activities.0.activity_id', $activityWithOnlyCoa1->id)
      ->set('workPlans.0.activities.0.budget_items.0.quantity', 2)
      ->set('workPlans.0.activities.0.budget_items.0.unit_price', 5000); // total 10000

    // Distribute monthly budget
    $component->call('toggleMonth', 0, 0, 0, 1)
      ->set('workPlans.0.activities.0.budget_items.0.monthly_distribution.1', 10000);

    // Distribute cash out: exceeding total budget (allocated 15000)
    $component->call('toggleCashOutMonth', 0, 0, 0, 1)
      ->set('workPlans.0.activities.0.budget_items.0.cash_out_distribution.1', 15000);

    // Try to submit for review, should fail validation for cash out
    $component->call('submitForReview')
      ->assertHasErrors(['workPlans.0.activities.0.budget_items.0.cash_out']);
  }

  public function test_difference_group_selection_and_exclusion_on_submission_form(): void
  {
    $this->actingAs($this->user);

    // 1. Create two Difference Groups
    $dg1 = \App\Models\DifferenceGroup::create(['code' => 'DG1', 'name' => 'Difference Group 1']);
    $dg2 = \App\Models\DifferenceGroup::create(['code' => 'DG2', 'name' => 'Difference Group 2']);

    // 2. Map Coa1 to both Difference Groups
    $this->coa1->differenceGroups()->sync([$dg1->id, $dg2->id]);

    $component = Livewire::test(RkapSubmissionForm::class, ['periodId' => $this->period->id])
      ->set('workPlans.0.work_plan_id', $this->workPlan->id)
      ->set('workPlans.0.activities.0.activity_id', $this->activityWithCoas->id)
      ->set('workPlans.0.activities.0.budget_items.0.quantity', 0)
      ->set('workPlans.0.activities.0.budget_items.0.unit_price', 0)
      ->set('workPlans.0.activities.0.budget_items.0.difference_group_id', $dg1->id);

    // Verify that since values are 0 (not inputted yet), the group ID is not considered used
    $this->assertCount(0, $component->instance()->getUsedDifferenceGroupIds(0, 0, 1));

    // Now set positive values
    $component->set('workPlans.0.activities.0.budget_items.0.quantity', 1)
      ->set('workPlans.0.activities.0.budget_items.0.unit_price', 1000);

    // Add a second budget item using duplicate (so index 1 also has coa1)
    $component->call('duplicateBudgetItem', 0, 0, 0);

    // Verify that for the second item (index 1, same COA), dg1 is considered used
    $usedBeforeSecondSelect = $component->instance()->getUsedDifferenceGroupIds(0, 0, 1);
    $this->assertCount(1, $usedBeforeSecondSelect);
    $this->assertEquals($dg1->id, $usedBeforeSecondSelect[0]);

    // Verify that for the third item (index 2, different COA coa2), dg1 is NOT considered used
    $this->assertCount(0, $component->instance()->getUsedDifferenceGroupIds(0, 0, 2));

    // The second item should have the same coa1, let's select dg2 for it
    $component->set('workPlans.0.activities.0.budget_items.1.difference_group_id', $dg2->id);

    // Distribute monthly distribution for both so they save successfully
    $component->call('toggleMonth', 0, 0, 0, 1)
      ->set('workPlans.0.activities.0.budget_items.0.monthly_distribution.1', 1000)
      ->call('toggleMonth', 0, 0, 1, 1)
      ->set('workPlans.0.activities.0.budget_items.1.monthly_distribution.1', 1000);

    // Verify used difference groups list
    $used = $component->instance()->getUsedDifferenceGroupIds(0, 0, 0);
    $this->assertCount(1, $used);
    $this->assertEquals($dg2->id, $used[0]);

    $usedForSecond = $component->instance()->getUsedDifferenceGroupIds(0, 0, 1);
    $this->assertCount(1, $usedForSecond);
    $this->assertEquals($dg1->id, $usedForSecond[0]);

    // Save draft
    $component->call('saveDraft')
      ->assertHasNoErrors();

    // Verify database records
    $submissionId = $component->get('submissionId');
    $budgetItems = \App\Models\RkapBudgetItem::whereHas('workPlan', fn($q) => $q->where('rkap_submission_id', $submissionId))
      ->whereNotNull('difference_group_id')
      ->orderBy('id')
      ->get();
    $this->assertCount(2, $budgetItems);
    $this->assertEquals($dg1->id, $budgetItems[0]->difference_group_id);
    $this->assertEquals($dg2->id, $budgetItems[1]->difference_group_id);
  }

  public function test_is_gain_toggle_for_account_7603000001(): void
  {
    $this->actingAs($this->user);

    $gainLossCoa = Coa::create([
      'code' => '7603000001',
      'title' => 'Profit/Loss due to currency exchange differences',
    ]);

    $activity = Activity::create([
      'work_plan_id' => $this->workPlan->id,
      'code' => 'ACT_GAIN',
      'title' => 'Activity Gain Loss',
    ]);
    $activity->coas()->attach([$gainLossCoa->id]);

    $component = Livewire::test(RkapSubmissionForm::class, ['periodId' => $this->period->id])
      ->set('workPlans.0.work_plan_id', $this->workPlan->id)
      ->set('workPlans.0.activities.0.activity_id', $activity->id)
      ->set('workPlans.0.activities.0.budget_items.0.quantity', 1)
      ->set('workPlans.0.activities.0.budget_items.0.unit_price', 50000)
      ->set('workPlans.0.activities.0.budget_items.0.is_gain', false)
      ->call('toggleMonth', 0, 0, 0, 1)
      ->set('workPlans.0.activities.0.budget_items.0.monthly_distribution.1', 50000);

    $component->call('saveDraft')
      ->assertHasNoErrors();

    $submissionId = $component->get('submissionId');
    $item = \App\Models\RkapBudgetItem::whereHas('workPlan', fn($q) => $q->where('rkap_submission_id', $submissionId))
      ->where('account_code', '7603000001')
      ->first();

    $this->assertNotNull($item);
    $this->assertFalse($item->is_gain);
  }

  public function test_coa_7603000001_accumulation_multiplies_by_negative_one_when_is_gain_is_true(): void
  {
    $submission = \App\Models\RkapSubmission::create([
      'rkap_period_id' => $this->period->id,
      'bureau_id' => $this->user->bureau_id,
      'status' => 'draft',
    ]);

    $wp = \App\Models\RkapWorkPlan::create([
      'rkap_submission_id' => $submission->id,
      'work_plan_id' => $this->workPlan->id,
    ]);

    \App\Models\RkapBudgetItem::create([
      'rkap_work_plan_id' => $wp->id,
      'account_code' => '7603000001',
      'description' => 'Gain Item',
      'quantity' => 1,
      'unit_price' => 100000,
      'total_price' => 100000,
      'is_gain' => true,
    ]);

    \App\Models\RkapBudgetItem::create([
      'rkap_work_plan_id' => $wp->id,
      'account_code' => '7603000001',
      'description' => 'Loss Item',
      'quantity' => 1,
      'unit_price' => 40000,
      'total_price' => 40000,
      'is_gain' => false,
    ]);

    \App\Models\RkapBudgetItem::create([
      'rkap_work_plan_id' => $wp->id,
      'account_code' => '5101000001',
      'description' => 'Expense Item',
      'quantity' => 1,
      'unit_price' => 500000,
      'total_price' => 500000,
    ]);

    $wp->unsetRelation('budgetItems');
    // Expected total: -100,000 (Gain) + 40,000 (Loss) + 500,000 = 440,000
    $this->assertEquals(440000, $wp->total_budget);
  }
}
