<?php

namespace App\Services;

use App\Models\RkapBudgetItem;
use App\Models\RkapBudgetItemProjection;
use App\Models\RkapPeriod;
use Illuminate\Support\Facades\DB;

class ProjectionRecalculationService
{
    /**
     * Recalculate and synchronize total projections (and monthly projection records)
     * for budget items that already have monthly projections saved (Option B).
     *
     * @param array|int $budgetItemIds Single ID or array of budget item IDs
     * @param int $periodId
     * @param int|null $userId
     * @param string|null $batchId
     * @param string $source
     * @return int Number of budget items updated
     */
    public static function recalculateForBudgetItems(
        array|int $budgetItemIds,
        int $periodId,
        ?int $userId = null,
        ?string $batchId = null,
        string $source = 'realization_sync'
    ): int {
        $ids = is_array($budgetItemIds) ? array_values(array_unique(array_filter($budgetItemIds))) : [$budgetItemIds];
        if (empty($ids)) {
            return 0;
        }

        $period = RkapPeriod::find($periodId);
        if (!$period) {
            return 0;
        }

        // Fetch budget items with their relationships
        $budgetItems = RkapBudgetItem::with(['projections', 'realizations', 'monthlies'])
            ->whereIn('id', $ids)
            ->get();

        $updatedCount = 0;

        foreach ($budgetItems as $budgetItem) {
            // Option B: Only update budget items that have monthly projections stored in rkap_budget_item_projections
            if ($budgetItem->projections->count() === 0) {
                continue;
            }

            $oldMonthly = [];
            for ($m = 1; $m <= 12; $m++) {
                $existing = $budgetItem->projections->where('month', $m)->first();
                $oldMonthly[$m] = $existing ? (float) $existing->amount : 0.00;
            }
            $oldTotal = (float) $budgetItem->projection;

            $newMonthly = [];
            $totalProj = 0.00;

            for ($m = 1; $m <= 12; $m++) {
                $realizations = $budgetItem->realizations->where('month', $m);
                $hasRealization = $realizations->count() > 0;
                $realizationAmount = (float) $realizations->sum('amount');
                $isClosed = $period->isMonthClosed($m);

                if ($hasRealization) {
                    // Month has realization: use realization amount
                    $amount = $realizationAmount;
                } elseif ($isClosed) {
                    // Closed without realization: forced to 0 (matching saveMonthlyProjections behavior)
                    $amount = 0.00;
                } else {
                    // Open without realization: keep existing projection amount if set, or 0
                    $existing = $budgetItem->projections->where('month', $m)->first();
                    $amount = $existing ? (float) $existing->amount : 0.00;
                }

                $newMonthly[$m] = $amount;
                $totalProj += $amount;

                // Sync the individual monthly projection row in DB
                RkapBudgetItemProjection::updateOrCreate(
                    [
                        'rkap_budget_item_id' => $budgetItem->id,
                        'month'               => $m,
                    ],
                    [
                        'rkap_period_id'      => $periodId,
                        'amount'              => $amount,
                        'inputted_by'         => $userId ?? auth()->id(),
                    ]
                );
            }

            $budgetItem->update(['projection' => $totalProj]);

            // Audit log
            ProjectionAuditService::logChange(
                $budgetItem,
                $periodId,
                $userId ?? auth()->id(),
                $oldMonthly,
                $oldTotal,
                $newMonthly,
                $totalProj,
                $source,
                $batchId,
                'monthly',
                'Sinkronisasi otomatis dengan data realisasi'
            );

            $updatedCount++;
        }

        // Flush analytics cache if items were updated
        if ($updatedCount > 0) {
            AnalyticsCacheService::flushPeriod($periodId);
        }

        return $updatedCount;
    }

    /**
     * Recalculate projections for all budget items in an RKAP period that have monthly projections.
     *
     * @param int $periodId
     * @param int|null $userId
     * @return int Number of budget items updated
     */
    public static function recalculateForPeriod(int $periodId, ?int $userId = null): int
    {
        $budgetItemIds = RkapBudgetItem::whereHas('workPlan.submission', function ($q) use ($periodId) {
            $q->where('rkap_period_id', $periodId);
        })
        ->whereHas('projections')
        ->pluck('id')
        ->toArray();

        return self::recalculateForBudgetItems($budgetItemIds, $periodId, $userId, null, 'period_sync');
    }
}
