<?php

namespace App\Services;

use App\Models\RkapPeriod;
use App\Models\RkapSubmission;
use App\Models\RkapWorkPlan;
use App\Models\RkapBudgetItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

class RkapBulkDuplicationService
{
    /**
     * Duplicate all submissions from a source period to a target period.
     *
     * @param RkapPeriod $sourcePeriod
     * @param RkapPeriod $targetPeriod
     * @param User $adminUser
     * @return array
     * @throws Exception
     */
    public function duplicateAllSubmissions(RkapPeriod $sourcePeriod, RkapPeriod $targetPeriod, User $adminUser): array
    {
        if (!$targetPeriod->isOpen()) {
            throw new Exception(__('Periode tujuan harus dalam status Open.'));
        }

        if ($sourcePeriod->id === $targetPeriod->id) {
            throw new Exception(__('Periode sumber dan periode tujuan tidak boleh sama.'));
        }

        // Get all submissions in the source period
        $sourceSubmissions = RkapSubmission::with([
            'bureau',
            'workPlans.activityFiles',
            'workPlans.budgetItems.monthlies',
            'workPlans.budgetItems.cashOuts',
            'workPlans.budgetItems.realizations',
            'workPlans.budgetItems.projections',
        ])->where('rkap_period_id', $sourcePeriod->id)->get();

        if ($sourceSubmissions->isEmpty()) {
            return [
                'duplicated' => 0,
                'skipped' => 0,
                'skipped_bureaus' => [],
                'message' => __('Tidak ada pengajuan RKAP di periode sumber.'),
            ];
        }

        // Get list of bureau IDs that already have a submission in target period
        $existingBureauIds = RkapSubmission::where('rkap_period_id', $targetPeriod->id)
            ->pluck('bureau_id')
            ->toArray();

        $duplicatedCount = 0;
        $skippedCount = 0;
        $skippedBureaus = [];

        DB::transaction(function () use (
            $sourceSubmissions,
            $existingBureauIds,
            $targetPeriod,
            $sourcePeriod,
            $adminUser,
            &$duplicatedCount,
            &$skippedCount,
            &$skippedBureaus
        ) {
            foreach ($sourceSubmissions as $sourceSub) {
                if (in_array($sourceSub->bureau_id, $existingBureauIds, true)) {
                    $skippedCount++;
                    $skippedBureaus[] = $sourceSub->bureau ? $sourceSub->bureau->name : "Bureau ID {$sourceSub->bureau_id}";
                    continue;
                }

                // Create new submission
                $newSubmission = RkapSubmission::create([
                    'rkap_period_id' => $targetPeriod->id,
                    'bureau_id'      => $sourceSub->bureau_id,
                    'created_by'     => $adminUser->id,
                    'current_version' => 1,
                    'status'         => 'draft',
                    'total_budget'   => $sourceSub->total_budget,
                    'notes'          => 'Duplikasi massal dari periode: ' . $sourcePeriod->title,
                ]);

                foreach ($sourceSub->workPlans as $wp) {
                    $newWp = RkapWorkPlan::create([
                        'rkap_submission_id'     => $newSubmission->id,
                        'work_plan_id'           => $wp->work_plan_id,
                        'activity_id'            => $wp->activity_id,
                        'program_code'           => $wp->program_code,
                        'program_name'           => $wp->program_name,
                        'description'            => $wp->description,
                        'output_target'          => $wp->output_target,
                        'unit'                   => $wp->unit,
                        'quantity'               => $wp->quantity,
                        'sort_order'             => $wp->sort_order,
                        'approval_status'        => 'pending',
                        'revision_notes'         => null,
                        'added_by_verifier'      => false,
                        'is_past_period_payment' => $wp->is_past_period_payment,
                        'past_period_id'         => $wp->past_period_id,
                    ]);

                    // Duplicate activity files
                    foreach ($wp->activityFiles as $file) {
                        $newPath = $file->file_path;
                        if ($file->file_path && Storage::exists($file->file_path)) {
                            $ext = pathinfo($file->file_path, PATHINFO_EXTENSION);
                            $newPath = 'rkap_files/' . Str::uuid() . ($ext ? '.' . $ext : '');
                            try {
                                Storage::copy($file->file_path, $newPath);
                            } catch (\Throwable $t) {
                                $newPath = $file->file_path;
                            }
                        }

                        $newWp->activityFiles()->create([
                            'file_name'     => $file->file_name,
                            'original_name' => $file->original_name,
                            'file_path'     => $newPath,
                            'file_type'     => $file->file_type,
                            'file_size'     => $file->file_size,
                        ]);
                    }

                    // Duplicate budget items
                    foreach ($wp->budgetItems as $bi) {
                        $newBi = RkapBudgetItem::create([
                            'rkap_work_plan_id'   => $newWp->id,
                            'account_code'        => $bi->account_code,
                            'description'         => $bi->description,
                            'unit'                => $bi->unit,
                            'quantity'            => $bi->quantity,
                            'unit_2'              => $bi->unit_2,
                            'quantity_2'          => $bi->quantity_2,
                            'unit_price'          => $bi->unit_price,
                            'total_price'         => $bi->total_price,
                            'projection'          => $bi->projection,
                            'remarks'             => $bi->remarks,
                            'flow_direction'      => $bi->flow_direction ?? 'OUT',
                            'difference_group_id' => $bi->difference_group_id,
                            'is_gain'             => $bi->is_gain ?? true,
                        ]);

                        // Duplicate monthlies
                        foreach ($bi->monthlies as $monthly) {
                            $newBi->monthlies()->create([
                                'month'  => $monthly->month,
                                'amount' => $monthly->amount,
                            ]);
                        }

                        // Duplicate cash outs
                        foreach ($bi->cashOuts as $cashOut) {
                            $newBi->cashOuts()->create([
                                'month'  => $cashOut->month,
                                'amount' => $cashOut->amount,
                            ]);
                        }

                        // Duplicate realizations
                        foreach ($bi->realizations as $realization) {
                            $newBi->realizations()->create([
                                'rkap_period_id' => $targetPeriod->id,
                                'month'          => $realization->month,
                                'amount'         => $realization->amount,
                                'uploaded_by'    => $adminUser->id,
                                'uploaded_at'    => $realization->uploaded_at ?? now(),
                            ]);
                        }

                        // Duplicate projections
                        foreach ($bi->projections as $projection) {
                            $newBi->projections()->create([
                                'rkap_period_id' => $targetPeriod->id,
                                'month'          => $projection->month,
                                'amount'         => $projection->amount,
                                'inputted_by'    => $adminUser->id,
                            ]);
                        }
                    }
                }

                $newSubmission->calculateTotalBudget();
                $duplicatedCount++;
            }
        });

        // Flush period cache
        AnalyticsCacheService::flushPeriod($targetPeriod->id);

        return [
            'duplicated'      => $duplicatedCount,
            'skipped'         => $skippedCount,
            'skipped_bureaus' => $skippedBureaus,
        ];
    }
}
