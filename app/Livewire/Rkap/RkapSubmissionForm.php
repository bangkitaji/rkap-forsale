<?php

namespace App\Livewire\Rkap;

use Livewire\Component;
use App\Models\RkapSubmission;
use App\Models\RkapPeriod;
use App\Models\RkapWorkPlan;
use App\Models\RkapBudgetItem;
use App\Models\WorkPlan;
use App\Models\Activity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RkapSubmissionForm extends Component
{
    public ?int $submissionId = null;
    public ?int $periodId = null;

    // Submission fields
    public string $notes = '';

    // Work plans (array of work plan data)
    public array $workPlans = [];

    public ?RkapSubmission $submission = null;
    public ?RkapPeriod $period = null;

    public function mount(?int $periodId = null, ?int $id = null): void
    {
        if ($id) {
            $this->submission = RkapSubmission::with('workPlans.budgetItems')->findOrFail($id);
            $this->submissionId = $id;
            $this->periodId = $this->submission->rkap_period_id;
            $this->period = $this->submission->period;
            $this->notes = $this->submission->notes ?? '';
            $this->loadWorkPlans();
        } elseif ($periodId) {
            $this->periodId = $periodId;
            $this->period = RkapPeriod::findOrFail($periodId);
            $this->addWorkPlan();
        }
    }

    /**
     * Reset activity_id whenever work_plan_id changes for a given row.
     */
    public function updated(string $name): void
    {
        if (preg_match('/^workPlans\.(\d+)\.work_plan_id$/', $name, $m)) {
            $idx = (int) $m[1];
            $this->workPlans[$idx]['activity_id'] = null;
        }
    }

    /**
     * All available WorkPlans (with their activities) for the Program Kerja select.
     */
    public function getWorkPlanOptionsProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return WorkPlan::with('activities')->orderBy('code')->get();
    }

    /**
     * Activities filtered by the selected work_plan_id for a given row index.
     */
    public function getActivitiesForIndex(int $wpIndex): \Illuminate\Database\Eloquent\Collection
    {
        $workPlanId = $this->workPlans[$wpIndex]['work_plan_id'] ?? null;

        if (!$workPlanId) {
            return collect();
        }

        return Activity::where('work_plan_id', $workPlanId)->orderBy('code')->get();
    }

    private function loadWorkPlans(): void
    {
        $this->workPlans = $this->submission->workPlans->map(function ($wp) {
            return [
                'id'            => $wp->id,
                'work_plan_id'  => $wp->work_plan_id,
                'activity_id'   => $wp->activity_id,
                'description'   => $wp->description ?? '',
                'output_target' => $wp->output_target ?? '',
                'unit'          => $wp->unit ?? '',
                'quantity'      => $wp->quantity,
                'sort_order'    => $wp->sort_order,
                'budget_items'  => $wp->budgetItems->map(function ($bi) {
                    return [
                        'id'           => $bi->id,
                        'account_code' => $bi->account_code ?? '',
                        'description'  => $bi->description,
                        'unit'         => $bi->unit ?? '',
                        'quantity'     => $bi->quantity,
                        'unit_price'   => $bi->unit_price,
                        'remarks'      => $bi->remarks ?? '',
                    ];
                })->toArray(),
            ];
        })->toArray();
    }

    public function addWorkPlan(): void
    {
        $this->workPlans[] = [
            'id'            => null,
            'work_plan_id'  => null,
            'activity_id'   => null,
            'description'   => '',
            'output_target' => '',
            'unit'          => '',
            'quantity'      => 1,
            'sort_order'    => count($this->workPlans),
            'budget_items'  => [[
                'id'           => null,
                'account_code' => '',
                'description'  => '',
                'unit'         => '',
                'quantity'     => 1,
                'unit_price'   => 0,
                'remarks'      => '',
            ]],
        ];
    }

    public function removeWorkPlan(int $index): void
    {
        unset($this->workPlans[$index]);
        $this->workPlans = array_values($this->workPlans);
    }

    public function addBudgetItem(int $wpIndex): void
    {
        $this->workPlans[$wpIndex]['budget_items'][] = [
            'id'           => null,
            'account_code' => '',
            'description'  => '',
            'unit'         => '',
            'quantity'     => 1,
            'unit_price'   => 0,
            'remarks'      => '',
        ];
    }

    public function removeBudgetItem(int $wpIndex, int $biIndex): void
    {
        unset($this->workPlans[$wpIndex]['budget_items'][$biIndex]);
        $this->workPlans[$wpIndex]['budget_items'] = array_values($this->workPlans[$wpIndex]['budget_items']);
    }

    public function getGrandTotalProperty(): float
    {
        $total = 0;
        foreach ($this->workPlans as $wp) {
            foreach ($wp['budget_items'] as $bi) {
                $total += (float)($bi['quantity'] ?? 0) * (float)($bi['unit_price'] ?? 0);
            }
        }
        return $total;
    }

    protected function rules(): array
    {
        return [
            'notes'                                          => 'nullable|string',
            'workPlans'                                      => 'required|array|min:1',
            'workPlans.*.work_plan_id'                       => 'required|integer|exists:work_plans,id',
            'workPlans.*.activity_id'                        => 'nullable|integer|exists:activities,id',
            'workPlans.*.quantity'                           => 'required|integer|min:1',
            'workPlans.*.budget_items'                       => 'required|array|min:1',
            'workPlans.*.budget_items.*.description'         => 'required|string|max:255',
            'workPlans.*.budget_items.*.quantity'            => 'required|integer|min:1',
            'workPlans.*.budget_items.*.unit_price'          => 'required|numeric|min:0',
        ];
    }

    public function saveDraft(): void
    {
        $this->validate();
        $this->saveSubmission('draft');
        session()->flash('message', 'Draft RKAP berhasil disimpan.');
    }

    public function submitForReview(): void
    {
        $this->validate();
        $submission = $this->saveSubmission('draft');

        if ($submission->status === 'draft') {
            $submission->submit();
        }

        session()->flash('message', 'RKAP berhasil diajukan untuk review.');
        $this->redirectRoute('rkap-submissions');
    }

    private function saveSubmission(string $status): RkapSubmission
    {
        return DB::transaction(function () use ($status) {
            $user = Auth::user();

            $submission = RkapSubmission::updateOrCreate(
                ['id' => $this->submissionId],
                [
                    'rkap_period_id' => $this->periodId,
                    'bureau_id'      => $user->bureau_id,
                    'created_by'     => $user->id,
                    'notes'          => $this->notes ?: null,
                    'status'         => $this->submissionId ? null : $status,
                ]
            );

            if ($this->submissionId) {
                $existingWpIds = collect($this->workPlans)->pluck('id')->filter()->toArray();
                $submission->workPlans()->whereNotIn('id', $existingWpIds)->delete();
            }

            foreach ($this->workPlans as $sortIdx => $wpData) {
                // Resolve program_name from the selected Activity (or WorkPlan as fallback)
                $programName = null;
                if (!empty($wpData['activity_id'])) {
                    $programName = Activity::find($wpData['activity_id'])?->title;
                }
                if (!$programName && !empty($wpData['work_plan_id'])) {
                    $programName = WorkPlan::find($wpData['work_plan_id'])?->title;
                }

                $workPlan = RkapWorkPlan::updateOrCreate(
                    ['id' => $wpData['id'] ?? null],
                    [
                        'rkap_submission_id' => $submission->id,
                        'work_plan_id'       => $wpData['work_plan_id'] ?: null,
                        'activity_id'        => $wpData['activity_id'] ?: null,
                        'program_name'       => $programName,     // kept for backward compatibility
                        'description'        => $wpData['description'] ?: null,
                        'output_target'      => $wpData['output_target'] ?: null,
                        'unit'               => $wpData['unit'] ?: null,
                        'quantity'           => $wpData['quantity'],
                        'sort_order'         => $sortIdx,
                    ]
                );

                $existingBiIds = collect($wpData['budget_items'])->pluck('id')->filter()->toArray();
                $workPlan->budgetItems()->whereNotIn('id', $existingBiIds)->delete();

                foreach ($wpData['budget_items'] as $biData) {
                    RkapBudgetItem::updateOrCreate(
                        ['id' => $biData['id'] ?? null],
                        [
                            'rkap_work_plan_id' => $workPlan->id,
                            'account_code'  => $biData['account_code'] ?: null,
                            'description'   => $biData['description'],
                            'unit'          => $biData['unit'] ?: null,
                            'quantity'      => $biData['quantity'],
                            'unit_price'    => $biData['unit_price'],
                            'remarks'       => $biData['remarks'] ?: null,
                        ]
                    );
                }
            }

            $submission->calculateTotalBudget();
            $this->submissionId = $submission->id;
            return $submission->fresh();
        });
    }

    public function render()
    {
        return view('livewire.rkap.rkap-submission-form', [
            'workPlanOptions' => $this->workPlanOptions,
        ])->layout('layouts.contentNavbarLayout');
    }
}
