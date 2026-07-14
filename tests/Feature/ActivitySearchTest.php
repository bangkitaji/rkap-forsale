<?php

namespace Tests\Feature;

use Tests\TestCase;
use Livewire\Livewire;
use App\Livewire\MasterData\Activities;
use App\Models\WorkPlan;
use App\Models\Activity;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ActivitySearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_search_activities_without_ambiguous_column_error(): void
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

        // Test component search
        Livewire::test(Activities::class)
            ->assertStatus(200)
            ->set('search', 'ACT001')
            ->assertSee('Activity One')
            ->assertDontSee('Activity Two');
    }

    public function test_can_search_activities_by_mapped_coa(): void
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
        $coa1 = \App\Models\Coa::create([
            'code' => 'COA111',
            'title' => 'Sales Account',
            'description' => 'Desc Coa 1',
        ]);

        $coa2 = \App\Models\Coa::create([
            'code' => 'COA222',
            'title' => 'Expense Account',
            'description' => 'Desc Coa 2',
        ]);

        // Map $coa1 to $activity1
        $activity1->coas()->attach($coa1->id);
        // Map $coa2 to $activity2
        $activity2->coas()->attach($coa2->id);

        // Search by COA code
        Livewire::test(Activities::class)
            ->assertStatus(200)
            ->set('search', 'COA111')
            ->assertSee('Activity One')
            ->assertDontSee('Activity Two');

        // Search by COA title
        Livewire::test(Activities::class)
            ->assertStatus(200)
            ->set('search', 'Expense')
            ->assertSee('Activity Two')
            ->assertDontSee('Activity One');
    }
}
