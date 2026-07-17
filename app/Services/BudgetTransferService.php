<?php

namespace App\Services;

use App\Models\BudgetTransfer;
use App\Models\BudgetTransferItem;
use App\Models\RkapPeriod;
use App\Models\RkapSubmission;
use App\Models\RkapWorkPlan;
use App\Models\Bureau;
use App\Models\User;
use App\Enums\BudgetTransferStatus;
use App\Enums\SubmissionStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Exception;

class BudgetTransferService
{
    /**
     * Create a new budget transfer request.
     */
    public function createTransfer(User $user, int $periodId, int $targetBureauId, array $workPlanIds, ?string $notes): BudgetTransfer
    {
        $sourceBureau = $user->bureau;
        if (!$sourceBureau) {
            throw new Exception("Anda harus terasosiasi dengan Biro untuk mengajukan transfer.");
        }

        $targetBureau = Bureau::findOrFail($targetBureauId);
        if ($sourceBureau->id === $targetBureau->id) {
            throw new Exception("Tidak dapat melakukan transfer budget ke biro sendiri.");
        }

        if ($sourceBureau->department_id !== $targetBureau->department_id) {
            throw new Exception("Biro asal dan biro tujuan harus berada dalam satu departemen.");
        }

        $period = RkapPeriod::findOrFail($periodId);
        $currentYear = (int) date('Y');
        if (!in_array($period->year, [$currentYear, $currentYear - 1])) {
            throw new Exception("Hanya dapat melakukan transfer untuk periode RKAP tahun berjalan ({$currentYear}) atau tahun sebelumnya (" . ($currentYear - 1) . ").");
        }

        $sourceSubmission = RkapSubmission::where('rkap_period_id', $periodId)
            ->where('bureau_id', $sourceBureau->id)
            ->first();

        if (!$sourceSubmission) {
            throw new Exception("Biro Anda belum memiliki pengajuan RKAP untuk periode ini.");
        }

        if ($sourceSubmission->status !== SubmissionStatus::Approved->value) {
            throw new Exception("Transfer hanya dapat dilakukan apabila status pengajuan RKAP asal sudah Disetujui (Approved).");
        }

        if (empty($workPlanIds)) {
            throw new Exception("Silakan pilih minimal satu program kerja atau kegiatan untuk ditransfer.");
        }

        // Verify work plans belong to the source submission and are not already pending transfer
        $workPlans = RkapWorkPlan::where('rkap_submission_id', $sourceSubmission->id)
            ->whereIn('id', $workPlanIds)
            ->get();

        if ($workPlans->count() !== count($workPlanIds)) {
            throw new Exception("Beberapa program kerja yang dipilih tidak valid.");
        }

        foreach ($workPlans as $wp) {
            $isPending = BudgetTransferItem::where('rkap_work_plan_id', $wp->id)
                ->whereHas('transfer', function ($q) {
                    $q->where('status', BudgetTransferStatus::Pending->value);
                })->exists();

            if ($isPending) {
                throw new Exception("Program kerja '{$wp->program_name}' sudah berada dalam proses transfer lain yang sedang pending.");
            }
        }

        return DB::transaction(function () use ($sourceSubmission, $sourceBureau, $targetBureau, $periodId, $workPlans, $user, $notes) {
            // Snapshot and calculate total budget of selected workplans
            $totalAmount = 0;
            $itemsData = [];

            foreach ($workPlans as $wp) {
                // Load child records for snapshotting
                $wp->load(['budgetItems.monthlies', 'budgetItems.cashOuts', 'budgetItems.realizations', 'budgetItems.projections']);
                
                $snapshot = [
                    'id' => $wp->id,
                    'program_code' => $wp->program_code,
                    'program_name' => $wp->program_name,
                    'description' => $wp->description,
                    'output_target' => $wp->output_target,
                    'unit' => $wp->unit,
                    'quantity' => $wp->quantity,
                    'budget_items' => $wp->budgetItems->map(function ($bi) {
                        return [
                            'id' => $bi->id,
                            'account_code' => $bi->account_code,
                            'description' => $bi->description,
                            'unit' => $bi->unit,
                            'quantity' => $bi->quantity,
                            'unit_2' => $bi->unit_2,
                            'quantity_2' => $bi->quantity_2,
                            'unit_price' => $bi->unit_price,
                            'total_price' => $bi->total_price,
                            'remarks' => $bi->remarks,
                            'monthly_distribution' => $bi->monthlies->pluck('amount', 'month')->toArray(),
                            'cash_out_distribution' => $bi->cashOuts->pluck('amount', 'month')->toArray(),
                            'realizations' => $bi->realizations->map(fn($r) => ['month' => $r->month, 'amount' => $r->amount])->toArray(),
                            'projections' => $bi->projections->map(fn($p) => ['month' => $p->month, 'amount' => $p->amount])->toArray(),
                        ];
                    })->toArray(),
                ];

                $totalAmount += $wp->budgetItems->sum('total_price');
                $itemsData[] = [
                    'work_plan_id' => $wp->id,
                    'snapshot' => $snapshot,
                ];
            }

            $transfer = BudgetTransfer::create([
                'rkap_period_id' => $periodId,
                'source_bureau_id' => $sourceBureau->id,
                'target_bureau_id' => $targetBureau->id,
                'source_submission_id' => $sourceSubmission->id,
                'requested_by' => $user->id,
                'status' => BudgetTransferStatus::Pending->value,
                'notes' => $notes,
                'total_amount' => $totalAmount,
            ]);

            foreach ($itemsData as $item) {
                BudgetTransferItem::create([
                    'budget_transfer_id' => $transfer->id,
                    'rkap_work_plan_id' => $item['work_plan_id'],
                    'snapshot_data' => $item['snapshot'],
                ]);
            }

            return $transfer;
        });
    }

    /**
     * Approve a pending budget transfer request.
     */
    public function approveTransfer(BudgetTransfer $transfer, User $reviewer, ?string $reviewNotes): void
    {
        if (!$transfer->isPending()) {
            throw new Exception("Hanya pengajuan transfer pending yang dapat disetujui.");
        }

        $originalUser = Auth::user();
        Auth::login($reviewer);

        try {
            DB::transaction(function () use ($transfer, $reviewer, $reviewNotes) {
                // Find or create target submission
                $targetSubmission = RkapSubmission::where('rkap_period_id', $transfer->rkap_period_id)
                    ->where('bureau_id', $transfer->target_bureau_id)
                    ->first();

                if (!$targetSubmission) {
                    $targetSubmission = RkapSubmission::create([
                        'rkap_period_id' => $transfer->rkap_period_id,
                        'bureau_id' => $transfer->target_bureau_id,
                        'created_by' => $reviewer->id,
                        'status' => SubmissionStatus::Approved->value,
                        'total_budget' => 0,
                    ]);
                }

                // Move the work plans (updating the rkap_submission_id moves all child records)
                foreach ($transfer->items as $item) {
                    RkapWorkPlan::where('id', $item->rkap_work_plan_id)
                        ->update(['rkap_submission_id' => $targetSubmission->id]);
                }

                // Recalculate both submissions
                $transfer->sourceSubmission->calculateTotalBudget();
                $targetSubmission->calculateTotalBudget();

                // Create version snapshots as audit trail
                if ($transfer->sourceSubmission->versions()->where('version_number', $transfer->sourceSubmission->current_version)->exists()) {
                    $transfer->sourceSubmission->increment('current_version');
                    $transfer->sourceSubmission->refresh();
                }
                $transfer->sourceSubmission->createVersion('budget_transfer', "Transfer budget keluar ke biro {$transfer->targetBureau->name}");

                if ($targetSubmission->versions()->where('version_number', $targetSubmission->current_version)->exists()) {
                    $targetSubmission->increment('current_version');
                    $targetSubmission->refresh();
                }
                $targetSubmission->createVersion('budget_transfer', "Transfer budget masuk dari biro {$transfer->sourceBureau->name}");

                // Update transfer header
                $transfer->update([
                    'status' => BudgetTransferStatus::Approved->value,
                    'target_submission_id' => $targetSubmission->id,
                    'reviewed_by' => $reviewer->id,
                    'review_notes' => $reviewNotes,
                    'reviewed_at' => now(),
                ]);

                // Flush cache
                AnalyticsCacheService::flushPeriod($transfer->rkap_period_id);
            });
        } finally {
            if ($originalUser) {
                Auth::login($originalUser);
            } else {
                Auth::logout();
            }
        }
    }

    /**
     * Reject a pending budget transfer request.
     */
    public function rejectTransfer(BudgetTransfer $transfer, User $reviewer, ?string $reviewNotes): void
    {
        if (!$transfer->isPending()) {
            throw new Exception("Hanya pengajuan transfer pending yang dapat ditolak.");
        }

        $transfer->update([
            'status' => BudgetTransferStatus::Rejected->value,
            'reviewed_by' => $reviewer->id,
            'review_notes' => $reviewNotes,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Cancel a pending budget transfer request.
     */
    public function cancelTransfer(BudgetTransfer $transfer, User $user): void
    {
        if (!$transfer->isPending()) {
            throw new Exception("Hanya pengajuan transfer pending yang dapat dibatalkan.");
        }

        if ($transfer->source_bureau_id !== $user->bureau_id) {
            throw new Exception("Hanya biro pengirim yang dapat membatalkan pengajuan transfer.");
        }

        $transfer->update([
            'status' => BudgetTransferStatus::Cancelled->value,
        ]);
    }
}
