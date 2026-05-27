<?php

namespace App\Livewire\Rkap;

use Livewire\Component;
use App\Models\RkapSubmission;
use App\Models\RkapPeriod;
use App\Models\RkapWorkPlan;
use App\Models\RkapBudgetItem;
use App\Models\WorkPlan;
use App\Models\Activity;
use App\Models\Coa;
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

    /**
     * Month labels (Indonesian).
     */
    public const MONTH_LABELS = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
        5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
        9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
    ];

    public function mount(?int $periodId = null, ?int $id = null): void
    {
        if ($id) {
            $this->submission = RkapSubmission::with(['workPlans.budgetItems.monthlies', 'workPlans.budgetItems.cashOuts'])->findOrFail($id);
            $this->submissionId = $id;
            $this->periodId = $this->submission->rkap_period_id;
            $this->period = $this->submission->period;
            $this->notes = $this->submission->notes ?? '';
            $this->loadWorkPlans();
        } elseif ($periodId) {
            $user = Auth::user();
            if ($user->bureau_id) {
                $exists = RkapSubmission::where('rkap_period_id', $periodId)
                    ->where('bureau_id', $user->bureau_id)
                    ->exists();
                if ($exists) {
                    session()->flash('error', 'Biro Anda sudah membuat pengajuan RKAP untuk periode ini.');
                    $this->redirectRoute('rkap-submissions');
                    return;
                }
            }

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
            $this->workPlans[$idx]['budget_items'] = [$this->emptyBudgetItem()];
        }

        if (preg_match('/^workPlans\.(\d+)\.activity_id$/', $name, $m)) {
            $idx = (int) $m[1];
            $activityId = $this->workPlans[$idx]['activity_id'] ?? null;
            if ($activityId) {
                $activity = Activity::with('coas')->find($activityId);
                if ($activity && $activity->coas->isNotEmpty()) {
                    $this->workPlans[$idx]['budget_items'] = $activity->coas->map(function ($coa) {
                        return array_merge($this->emptyBudgetItem(), [
                            'coa_id'       => $coa->id,
                            'account_code' => $coa->code,
                            'description'  => $coa->title,
                        ]);
                    })->toArray();
                } else {
                    $this->workPlans[$idx]['budget_items'] = [$this->emptyBudgetItem()];
                }
            } else {
                $this->workPlans[$idx]['budget_items'] = [$this->emptyBudgetItem()];
            }
        }
    }

    /**
     * Return a blank budget item array including monthly keys.
     */
    private function emptyBudgetItem(): array
    {
        return [
            'id'                     => null,
            'coa_id'                 => null,
            'account_code'           => '',
            'description'            => '',
            'unit'                   => '',
            'quantity'               => 1,
            'unit_price'             => 0,
            'remarks'                => '',
            'monthly_distribution'   => [],
            'distribution_months'    => [],
            'cash_out_distribution'  => [],
            'cash_out_months'        => [],
        ];
    }

    /**
     * All available WorkPlans (with their activities) for the Program Kerja select.
     */
    public function getWorkPlanOptionsProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return WorkPlan::with('activities')->orderBy('code')->get();
    }

    /**
     * All available COAs for the Budget Item select.
     */
    public function getCoaOptionsProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return Coa::orderBy('code')->get();
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

    /**
     * Get COA options for a specific work plan index, filtered by the selected activity if applicable.
     */
    public function getCoaOptionsForIndex(int $wpIndex): \Illuminate\Database\Eloquent\Collection
    {
        // Return all available COAs regardless of activity selection
        return $this->coaOptions;
    }

    /**
     * Get display label for a budget item's COA (code — title).
     * Falls back to account_code and description if COA lookup fails.
     */
    public function getCoaDisplayLabel(int $wpIndex, int $biIndex): string
    {
        $bi = $this->workPlans[$wpIndex]['budget_items'][$biIndex] ?? null;
        if (!$bi) {
            return '';
        }

        // Try to get from COA lookup first
        if (isset($bi['coa_id']) && $bi['coa_id']) {
            $coa = $this->coaOptions->firstWhere('id', $bi['coa_id']);
            if ($coa) {
                return $coa->code . ' — ' . $coa->title;
            }
        }

        // Fallback to account_code and description stored in budget item
        if (!empty($bi['account_code']) || !empty($bi['description'])) {
            return trim(($bi['account_code'] ?? '') . (!empty($bi['description']) ? ' — ' . ($bi['description'] ?? '') : ''));
        }

        return '';
    }

    // ── Monthly Distribution Methods ──

    /**
     * Toggle a month on/off for a specific budget item.
     */
    public function toggleMonth(int $wpIdx, int $biIdx, int $month): void
    {
        $months = $this->workPlans[$wpIdx]['budget_items'][$biIdx]['distribution_months'] ?? [];

        if (in_array($month, $months)) {
            $months = array_values(array_diff($months, [$month]));
            // Also remove the amount for this month
            unset($this->workPlans[$wpIdx]['budget_items'][$biIdx]['monthly_distribution'][$month]);
        } else {
            $months[] = $month;
            sort($months);
            // Initialize with 0
            $this->workPlans[$wpIdx]['budget_items'][$biIdx]['monthly_distribution'][$month] = 0;
        }

        $this->workPlans[$wpIdx]['budget_items'][$biIdx]['distribution_months'] = array_values($months);
    }

    /**
     * Distribute the total amount evenly across selected months.
     */
    public function distributeEvenly(int $wpIdx, int $biIdx): void
    {
        $bi = $this->workPlans[$wpIdx]['budget_items'][$biIdx];
        $total = (float) ($bi['quantity'] ?? 0) * (float) ($bi['unit_price'] ?? 0);
        $months = $bi['distribution_months'] ?? [];

        if (empty($months) || $total <= 0) {
            return;
        }

        $count = count($months);
        $perMonth = floor($total / $count);
        $remainder = $total - ($perMonth * $count);

        $distribution = [];
        foreach ($months as $i => $month) {
            // Add remainder to the last month to ensure exact match
            $distribution[$month] = ($i === $count - 1) ? $perMonth + $remainder : $perMonth;
        }

        $this->workPlans[$wpIdx]['budget_items'][$biIdx]['monthly_distribution'] = $distribution;
    }

    /**
     * Get the remaining (unallocated) amount for a budget item.
     */
    public function getMonthlyRemainder(int $wpIdx, int $biIdx): float
    {
        $bi = $this->workPlans[$wpIdx]['budget_items'][$biIdx] ?? null;
        if (!$bi) {
            return 0;
        }

        $total = (float) ($bi['quantity'] ?? 0) * (float) ($bi['unit_price'] ?? 0);
        $allocated = array_sum($bi['monthly_distribution'] ?? []);

        return $total - $allocated;
    }

    // ── Cash Out Plan Methods ──

    /**
     * Toggle a month on/off for a specific budget item's cash out plan.
     */
    public function toggleCashOutMonth(int $wpIdx, int $biIdx, int $month): void
    {
        $months = $this->workPlans[$wpIdx]['budget_items'][$biIdx]['cash_out_months'] ?? [];

        if (in_array($month, $months)) {
            $months = array_values(array_diff($months, [$month]));
            // Also remove the amount for this month
            unset($this->workPlans[$wpIdx]['budget_items'][$biIdx]['cash_out_distribution'][$month]);
        } else {
            $months[] = $month;
            sort($months);
            // Initialize with 0
            $this->workPlans[$wpIdx]['budget_items'][$biIdx]['cash_out_distribution'][$month] = 0;
        }

        $this->workPlans[$wpIdx]['budget_items'][$biIdx]['cash_out_months'] = array_values($months);
    }

    /**
     * Distribute the total amount evenly across selected cash out months.
     */
    public function distributeCashOutEvenly(int $wpIdx, int $biIdx): void
    {
        $bi = $this->workPlans[$wpIdx]['budget_items'][$biIdx];
        $total = (float) ($bi['quantity'] ?? 0) * (float) ($bi['unit_price'] ?? 0);
        $months = $bi['cash_out_months'] ?? [];

        if (empty($months) || $total <= 0) {
            return;
        }

        $count = count($months);
        $perMonth = floor($total / $count);
        $remainder = $total - ($perMonth * $count);

        $distribution = [];
        foreach ($months as $i => $month) {
            // Add remainder to the last month to ensure exact match
            $distribution[$month] = ($i === $count - 1) ? $perMonth + $remainder : $perMonth;
        }

        $this->workPlans[$wpIdx]['budget_items'][$biIdx]['cash_out_distribution'] = $distribution;
    }

    /**
     * Get the remaining (unallocated) cash out amount for a budget item.
     */
    public function getCashOutRemainder(int $wpIdx, int $biIdx): float
    {
        $bi = $this->workPlans[$wpIdx]['budget_items'][$biIdx] ?? null;
        if (!$bi) {
            return 0;
        }

        $total = (float) ($bi['quantity'] ?? 0) * (float) ($bi['unit_price'] ?? 0);
        $allocated = array_sum($bi['cash_out_distribution'] ?? []);

        return $total - $allocated;
    }

    // ── Work Plan / Budget Item Management ──

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
                    $coa = Coa::where('code', $bi->account_code)->first();
                    return [
                        'id'                     => $bi->id,
                        'coa_id'                 => $coa ? $coa->id : null,
                        'account_code'           => $bi->account_code ?? '',
                        'description'            => $bi->description,
                        'unit'                   => $bi->unit ?? '',
                        'quantity'               => $bi->quantity,
                        'unit_price'             => $bi->unit_price,
                        'remarks'                => $bi->remarks ?? '',
                        'monthly_distribution'   => $bi->monthlies->pluck('amount', 'month')->map(fn($v) => (float) $v)->toArray(),
                        'distribution_months'    => $bi->monthlies->pluck('month')->toArray(),
                        'cash_out_distribution'  => $bi->cashOuts->pluck('amount', 'month')->map(fn($v) => (float) $v)->toArray(),
                        'cash_out_months'        => $bi->cashOuts->pluck('month')->toArray(),
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
            'budget_items'  => [$this->emptyBudgetItem()],
        ];
    }

    public function removeWorkPlan(int $index): void
    {
        unset($this->workPlans[$index]);
        $this->workPlans = array_values($this->workPlans);
    }

    public function addBudgetItem(int $wpIndex): void
    {
        $this->workPlans[$wpIndex]['budget_items'][] = $this->emptyBudgetItem();
    }

    public function removeBudgetItem(int $wpIndex, int $biIndex): void
    {
        unset($this->workPlans[$wpIndex]['budget_items'][$biIndex]);
        $this->workPlans[$wpIndex]['budget_items'] = array_values($this->workPlans[$wpIndex]['budget_items']);
    }

    public function duplicateBudgetItem(int $wpIndex, int $biIndex): void
    {
        $sourceItem = $this->workPlans[$wpIndex]['budget_items'][$biIndex];
        
        $newItem = array_merge($this->emptyBudgetItem(), [
            'coa_id'       => $sourceItem['coa_id'] ?? null,
            'account_code' => $sourceItem['account_code'] ?? '',
            'description'  => $sourceItem['description'] ?? '',
        ]);
        
        array_splice($this->workPlans[$wpIndex]['budget_items'], $biIndex + 1, 0, [$newItem]);
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
            'workPlans.*.budget_items.*.coa_id'              => 'required|integer|exists:coas,id',
            'workPlans.*.budget_items.*.quantity'            => 'required|integer|min:1',
            'workPlans.*.budget_items.*.unit_price'          => 'required|numeric|min:0',
        ];
    }

    public function saveDraft(): void
    {
        $this->validate();
        $this->validateBudgetItemsCoaMapping();
        $this->saveSubmission('draft');
        session()->flash('message', 'Draft RKAP berhasil disimpan.');
    }

    public function submitForReview(): void
    {
        $this->validate();
        $this->validateBudgetItemsCoaMapping();
        $this->validateMonthlyDistribution();
        $this->validateCashOutPlan();

        $submission = $this->saveSubmission('draft');

        if ($submission->status === 'draft') {
            $submission->submit();
        }

        session()->flash('message', 'RKAP berhasil diajukan untuk review.');
        $this->redirectRoute('rkap-submissions');
    }

    /**
     * Enforce: every selected budget_item COA must belong to the selected Activity for that row.
     * If activity_id is null, we reject non-null coa_id to prevent choosing COA not mapped to activity.
     */
    private function validateBudgetItemsCoaMapping(): void
    {
        // Validate that if COA is selected, Activity must be selected
        foreach (($this->workPlans ?? []) as $wpData) {
            $activityId = $wpData['activity_id'] ?? null;

            foreach (($wpData['budget_items'] ?? []) as $biData) {
                $coaId = $biData['coa_id'] ?? null;

                if (!$activityId && !empty($coaId)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'workPlans' => 'Activity harus dipilih jika COA telah dipilih pada salah satu baris.',
                    ]);
                }
            }
        }
    }

    /**
     * Validate that every budget item has monthly distribution that exactly equals its total.
     * This validation is mandatory for submission (not for draft save).
     */
    private function validateMonthlyDistribution(): void
    {
        foreach ($this->workPlans as $wpIdx => $wpData) {
            foreach ($wpData['budget_items'] as $biIdx => $biData) {
                $total = (float) ($biData['quantity'] ?? 0) * (float) ($biData['unit_price'] ?? 0);
                $months = $biData['distribution_months'] ?? [];
                $distribution = $biData['monthly_distribution'] ?? [];

                if (empty($months)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "workPlans.{$wpIdx}.budget_items.{$biIdx}.monthly" => 'Distribusi bulanan wajib diisi. Pilih minimal 1 bulan.',
                    ]);
                }

                $allocated = 0;
                foreach ($distribution as $month => $amount) {
                    $allocated += (float) $amount;
                }

                // Must exactly equal (within floating point tolerance)
                if (abs($total - $allocated) > 0.01) {
                    $diff = $total - $allocated;
                    $diffFormatted = number_format(abs($diff), 0, ',', '.');
                    $direction = $diff > 0 ? "kurang Rp {$diffFormatted}" : "lebih Rp {$diffFormatted}";

                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "workPlans.{$wpIdx}.budget_items.{$biIdx}.monthly" => "Total distribusi bulanan harus sama dengan total item (Rp " . number_format($total, 0, ',', '.') . "). Saat ini {$direction}.",
                    ]);
                }
            }
        }
    }

    /**
     * Validate that every budget item has cash out plan that does not exceed its total.
     * This validation is mandatory for submission (not for draft save).
     */
    private function validateCashOutPlan(): void
    {
        foreach ($this->workPlans as $wpIdx => $wpData) {
            foreach ($wpData['budget_items'] as $biIdx => $biData) {
                $total = (float) ($biData['quantity'] ?? 0) * (float) ($biData['unit_price'] ?? 0);
                $months = $biData['cash_out_months'] ?? [];
                $distribution = $biData['cash_out_distribution'] ?? [];

                if (empty($months)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "workPlans.{$wpIdx}.budget_items.{$biIdx}.cash_out" => 'Rencana kas keluar wajib diisi. Pilih minimal 1 bulan.',
                    ]);
                }

                $allocated = 0;
                foreach ($distribution as $month => $amount) {
                    $allocated += (float) $amount;
                }

                // Must be less than or equal to total price (with small floating point tolerance)
                if ($allocated - $total > 0.01) {
                    $diff = $allocated - $total;
                    $diffFormatted = number_format($diff, 0, ',', '.');

                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "workPlans.{$wpIdx}.budget_items.{$biIdx}.cash_out" => "Total rencana kas keluar tidak boleh melebihi total item (Rp " . number_format($total, 0, ',', '.') . "). Saat ini lebih Rp {$diffFormatted}.",
                    ]);
                }

                if ($allocated <= 0) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "workPlans.{$wpIdx}.budget_items.{$biIdx}.cash_out" => "Jumlah rencana kas keluar harus lebih besar dari Rp 0.",
                    ]);
                }
            }
        }
    }

    private function saveSubmission(string $status): RkapSubmission
    {
        return DB::transaction(function () use ($status) {
            $user = Auth::user();

            $data = [
                'rkap_period_id' => $this->periodId,
                'bureau_id'      => $user->bureau_id,
                'created_by'     => $user->id,
                'notes'          => $this->notes ?: null,
            ];

            if (!$this->submissionId) {
                $data['status'] = $status;
            }

            $submission = RkapSubmission::updateOrCreate(
                ['id' => $this->submissionId],
                $data
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
                    $coa = Coa::find($biData['coa_id']);
                    $budgetItem = RkapBudgetItem::updateOrCreate(
                        ['id' => $biData['id'] ?? null],
                        [
                            'rkap_work_plan_id' => $workPlan->id,
                            'account_code'  => $coa ? $coa->code : null,
                            'description'   => $coa ? $coa->title : '',
                            'unit'          => $biData['unit'] ?: null,
                            'quantity'      => $biData['quantity'],
                            'unit_price'    => $biData['unit_price'],
                            'remarks'       => $biData['remarks'] ?: null,
                        ]
                    );

                    // Save monthly distribution
                    $budgetItem->monthlies()->delete();
                    $distribution = $biData['monthly_distribution'] ?? [];
                    foreach ($distribution as $month => $amount) {
                        if ((float) $amount > 0) {
                            $budgetItem->monthlies()->create([
                                'month'  => (int) $month,
                                'amount' => (float) $amount,
                            ]);
                        }
                    }

                    // Save cash out plan
                    $budgetItem->cashOuts()->delete();
                    $cashOutDistribution = $biData['cash_out_distribution'] ?? [];
                    foreach ($cashOutDistribution as $month => $amount) {
                        if ((float) $amount > 0) {
                            $budgetItem->cashOuts()->create([
                                'month'  => (int) $month,
                                'amount' => (float) $amount,
                            ]);
                        }
                    }
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
            'coaOptions'      => $this->coaOptions,
            'monthLabels'     => self::MONTH_LABELS,
        ])->layout('layouts.contentNavbarLayout');
    }
}
