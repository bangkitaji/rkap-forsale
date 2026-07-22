<?php

namespace App\Services;

use App\Models\BudgetTransfer;
use App\Models\BudgetTransferItem;
use App\Models\RkapPeriod;
use App\Models\RkapSubmission;
use App\Models\RkapWorkPlan;
use App\Models\RkapBudgetItem;
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
     * $itemsData can be:
     * 1. Array of work plan IDs: [1, 2]
     * 2. Array of item data arrays:
     *    [
     *      [
     *        'work_plan_id' => 1,
     *        'budget_item_id' => 10,
     *        'amount_transferred' => 2500000,
     *      ],
     *      ...
     *    ]
     */
    public function createTransfer(User $user, int $periodId, int $targetBureauId, array $itemsData, ?string $notes): BudgetTransfer
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

        if (empty($itemsData)) {
            throw new Exception("Silakan pilih minimal satu program kerja atau kegiatan untuk ditransfer.");
        }

        // Normalize items payload
        $normalizedItems = [];
        foreach ($itemsData as $item) {
            if (is_numeric($item)) {
                // Whole work plan transfer
                $wp = RkapWorkPlan::with('budgetItems.monthlies')->find($item);
                if (!$wp || $wp->rkap_submission_id !== $sourceSubmission->id) {
                    throw new Exception("Program kerja yang dipilih tidak valid.");
                }
                foreach ($wp->budgetItems as $bi) {
                    $normalizedItems[] = [
                        'work_plan_id' => $wp->id,
                        'budget_item_id' => $bi->id,
                        'amount_transferred' => (float) $bi->total_price,
                        'work_plan' => $wp,
                        'budget_item' => $bi,
                    ];
                }
            } elseif (is_array($item)) {
                $wpId = $item['work_plan_id'] ?? null;
                $biId = $item['budget_item_id'] ?? null;
                $amount = (float) ($item['amount_transferred'] ?? 0);

                if (!$wpId || !$biId || $amount <= 0) {
                    throw new Exception("Data transfer item tidak valid atau nominal 0.");
                }

                $wp = RkapWorkPlan::find($wpId);
                $bi = RkapBudgetItem::with('monthlies')->find($biId);

                if (!$wp || $wp->rkap_submission_id !== $sourceSubmission->id || !$bi || $bi->rkap_work_plan_id !== $wp->id) {
                    throw new Exception("Kegiatan/Budget item yang dipilih tidak valid.");
                }

                if ($amount > (float) $bi->total_price) {
                    throw new Exception("Nominal transfer (" . number_format($amount, 0, ',', '.') . ") melebihi budget item '" . $bi->description . "' (" . number_format($bi->total_price, 0, ',', '.') . ").");
                }

                $normalizedItems[] = [
                    'work_plan_id' => $wp->id,
                    'budget_item_id' => $bi->id,
                    'amount_transferred' => $amount,
                    'work_plan' => $wp,
                    'budget_item' => $bi,
                ];
            }
        }

        if (empty($normalizedItems)) {
            throw new Exception("Tidak ada item budget yang valid untuk ditransfer.");
        }

        // Verify pending transfer for these budget items
        foreach ($normalizedItems as $nItem) {
            $isPending = BudgetTransferItem::where('rkap_budget_item_id', $nItem['budget_item_id'])
                ->whereHas('transfer', function ($q) {
                    $q->where('status', BudgetTransferStatus::Pending->value);
                })->exists();

            if ($isPending) {
                throw new Exception("Kegiatan '{$nItem['budget_item']->description}' sudah berada dalam proses transfer lain yang sedang pending.");
            }
        }

        return DB::transaction(function () use ($sourceSubmission, $sourceBureau, $targetBureau, $periodId, $normalizedItems, $user, $notes) {
            $totalAmount = 0;
            $itemsToCreate = [];

            foreach ($normalizedItems as $nItem) {
                $wp = $nItem['work_plan'];
                $bi = $nItem['budget_item'];
                $amount = $nItem['amount_transferred'];

                $snapshot = [
                    'work_plan_id' => $wp->id,
                    'program_code' => $wp->program_code,
                    'program_name' => $wp->program_name,
                    'description' => $wp->description,
                    'budget_item' => [
                        'id' => $bi->id,
                        'account_code' => $bi->account_code,
                        'description' => $bi->description,
                        'unit' => $bi->unit,
                        'quantity' => $bi->quantity,
                        'unit_price' => $bi->unit_price,
                        'total_price' => $bi->total_price,
                        'amount_transferred' => $amount,
                        'monthly_distribution' => $bi->monthlies->pluck('amount', 'month')->toArray(),
                    ],
                ];

                $totalAmount += $amount;
                $itemsToCreate[] = [
                    'work_plan_id' => $wp->id,
                    'budget_item_id' => $bi->id,
                    'amount_transferred' => $amount,
                    'monthly_distribution' => $bi->monthlies->pluck('amount', 'month')->toArray(),
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

            foreach ($itemsToCreate as $item) {
                BudgetTransferItem::create([
                    'budget_transfer_id' => $transfer->id,
                    'rkap_work_plan_id' => $item['work_plan_id'],
                    'rkap_budget_item_id' => $item['budget_item_id'],
                    'amount_transferred' => $item['amount_transferred'],
                    'monthly_distribution' => $item['monthly_distribution'],
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

                foreach ($transfer->items as $item) {
                    if ($item->rkap_budget_item_id && $item->amount_transferred > 0) {
                        $sourceBudgetItem = RkapBudgetItem::with(['workPlan', 'monthlies'])->find($item->rkap_budget_item_id);
                        if ($sourceBudgetItem) {
                            $sourceWorkPlan = $sourceBudgetItem->workPlan;
                            $origTotal = (float) $sourceBudgetItem->total_price;
                            $transferAmount = (float) $item->amount_transferred;
                            $ratio = $origTotal > 0 ? min(1, $transferAmount / $origTotal) : 0;

                            // 1. Deduct from Source Budget Item
                            $newSourceTotal = max(0, $origTotal - $transferAmount);
                            $sourceBudgetItem->total_price = $newSourceTotal;
                            if ((float) $sourceBudgetItem->quantity > 0) {
                                $sourceBudgetItem->unit_price = $newSourceTotal / $sourceBudgetItem->quantity;
                            } else {
                                $sourceBudgetItem->unit_price = $newSourceTotal;
                            }
                            $sourceBudgetItem->save();

                            // Deduct monthly distributions from Source
                            $transferredMonthly = [];
                            foreach ($sourceBudgetItem->monthlies as $monthly) {
                                $deduct = round($monthly->amount * $ratio, 2);
                                $transferredMonthly[$monthly->month] = $deduct;
                                $monthly->amount = max(0, $monthly->amount - $deduct);
                                $monthly->save();
                            }

                            // 2. Duplicate WorkPlan & BudgetItem in Target Submission
                            $targetWorkPlan = RkapWorkPlan::where('rkap_submission_id', $targetSubmission->id)
                                ->where('program_name', $sourceWorkPlan->program_name)
                                ->first();

                            if (!$targetWorkPlan) {
                                $targetWorkPlan = RkapWorkPlan::create([
                                    'rkap_submission_id' => $targetSubmission->id,
                                    'work_plan_id' => $sourceWorkPlan->work_plan_id,
                                    'activity_id' => $sourceWorkPlan->activity_id,
                                    'program_code' => $sourceWorkPlan->program_code,
                                    'program_name' => $sourceWorkPlan->program_name,
                                    'description' => $sourceWorkPlan->description,
                                    'output_target' => $sourceWorkPlan->output_target,
                                    'unit' => $sourceWorkPlan->unit,
                                    'quantity' => $sourceWorkPlan->quantity,
                                    'sort_order' => $sourceWorkPlan->sort_order,
                                    'approval_status' => $sourceWorkPlan->approval_status,
                                ]);
                            }

                            $targetBudgetItem = RkapBudgetItem::create([
                                'rkap_work_plan_id' => $targetWorkPlan->id,
                                'account_code' => $sourceBudgetItem->account_code,
                                'description' => $sourceBudgetItem->description,
                                'unit' => $sourceBudgetItem->unit,
                                'quantity' => $sourceBudgetItem->quantity ?? 1,
                                'unit_2' => $sourceBudgetItem->unit_2,
                                'quantity_2' => $sourceBudgetItem->quantity_2,
                                'unit_price' => $sourceBudgetItem->quantity > 0 ? ($transferAmount / $sourceBudgetItem->quantity) : $transferAmount,
                                'total_price' => $transferAmount,
                                'remarks' => $sourceBudgetItem->remarks,
                                'flow_direction' => $sourceBudgetItem->flow_direction,
                                'difference_group_id' => $sourceBudgetItem->difference_group_id,
                            ]);

                            foreach ($transferredMonthly as $m => $mAmount) {
                                $targetBudgetItem->monthlies()->create([
                                    'month' => $m,
                                    'amount' => $mAmount,
                                ]);
                            }
                        }
                    } else {
                        // Fallback for whole workplan transfer
                        RkapWorkPlan::where('id', $item->rkap_work_plan_id)
                            ->update(['rkap_submission_id' => $targetSubmission->id]);
                    }
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
                if (class_exists(AnalyticsCacheService::class)) {
                    AnalyticsCacheService::flushPeriod($transfer->rkap_period_id);
                }
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
