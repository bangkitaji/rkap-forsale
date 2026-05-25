<?php

namespace Tests\Feature;

use Tests\TestCase;
use Livewire\Livewire;
use App\Livewire\MasterData\ActivityCoaMapping;
use App\Models\WorkPlan;
use App\Models\Activity;
use App\Models\Coa;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ActivityCoaMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_component_renders_successfully(): void
    {
        // WorkPlan
        $workPlan = WorkPlan::create([
            'code' => 'WP001',
            'title' => 'Test Work Plan',
        ]);

        // Activities
        $activity1 = Activity::create([
            'work_plan_id' => $workPlan->id,
            'code' => 'ACT001',
            'title' => 'Activity One',
            'description' => 'Desc 1',
        ]);

        $activity2 = Activity::create([
            'work_plan_id' => $workPlan->id,
            'code' => 'ACT002',
            'title' => 'Activity Two',
            'description' => 'Desc 2',
        ]);

        // COAs
        $coa1 = Coa::create([
            'code' => 'COA001',
            'title' => 'Coa One',
            'description' => 'Desc Coa 1',
        ]);

        $coa2 = Coa::create([
            'code' => 'COA002',
            'title' => 'Coa Two',
            'description' => 'Desc Coa 2',
        ]);

        // Test component instantiation
        Livewire::test(ActivityCoaMapping::class)
            ->assertStatus(200)
            ->assertSee('Activity ↔ COA Mapping')
            // Now select activity 1
            ->call('selectActivity', $activity1->id)
            ->assertSet('activityId', $activity1->id)
            // Check that selectedCoaIds is empty initially
            ->assertSet('selectedCoaIds', [])
            // Update selectedCoaIds
            ->set('selectedCoaIds', [$coa1->id, $coa2->id])
            // Call save
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Activity ↔ COA mapping saved successfully.');

        // Assert mapping is stored in DB
        $this->assertDatabaseHas('activity_coa', [
            'activity_id' => $activity1->id,
            'coa_id' => $coa1->id,
        ]);
        $this->assertDatabaseHas('activity_coa', [
            'activity_id' => $activity1->id,
            'coa_id' => $coa2->id,
        ]);
    }
}
