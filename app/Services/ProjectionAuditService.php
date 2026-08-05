<?php

namespace App\Services;

use App\Models\RkapBudgetItem;
use App\Models\RkapProjectionLog;

class ProjectionAuditService
{
    /**
     * Log a projection change for a given budget item.
     *
     * @param RkapBudgetItem $item
     * @param int $periodId
     * @param int|null $userId
     * @param array $oldMonthly Key-value array of month => amount [1 => 100, 2 => 200...]
     * @param float $oldTotal
     * @param array $newMonthly Key-value array of month => amount [1 => 150, 2 => 200...]
     * @param float $newTotal
     * @param string $source 'manual' or 'bulk_upload'
     * @param string|null $batchId Unique identifier for batch/bulk updates
     * @param string $inputMode 'monthly' or 'yearly'
     * @param string|null $notes Optional change note/reason
     * @return RkapProjectionLog|null
     */
    public static function logChange(
        RkapBudgetItem $item,
        int $periodId,
        ?int $userId,
        array $oldMonthly,
        float $oldTotal,
        array $newMonthly,
        float $newTotal,
        string $source = 'manual',
        ?string $batchId = null,
        string $inputMode = 'monthly',
        ?string $notes = null
    ): ?RkapProjectionLog {
        // Sanitize monthly arrays to floats with 2 decimals
        $sanitizedOldMonthly = [];
        for ($m = 1; $m <= 12; $m++) {
            $sanitizedOldMonthly[$m] = isset($oldMonthly[$m]) ? round((float)$oldMonthly[$m], 2) : 0.00;
        }

        $sanitizedNewMonthly = [];
        for ($m = 1; $m <= 12; $m++) {
            $sanitizedNewMonthly[$m] = isset($newMonthly[$m]) ? round((float)$newMonthly[$m], 2) : 0.00;
        }

        $oldTotalSanitized = round((float)$oldTotal, 2);
        $newTotalSanitized = round((float)$newTotal, 2);

        // Check if there is any meaningful change
        $monthlyChanged = json_encode($sanitizedOldMonthly) !== json_encode($sanitizedNewMonthly);
        $totalChanged = abs($oldTotalSanitized - $newTotalSanitized) > 0.001;

        if (!$monthlyChanged && !$totalChanged && empty($notes)) {
            return null; // No change to log
        }

        return RkapProjectionLog::create([
            'rkap_budget_item_id' => $item->id,
            'rkap_period_id'      => $periodId,
            'user_id'             => $userId,
            'batch_id'            => $batchId,
            'source'              => $source,
            'input_mode'          => $inputMode,
            'old_total'           => $oldTotalSanitized,
            'new_total'           => $newTotalSanitized,
            'old_monthly'         => $sanitizedOldMonthly,
            'new_monthly'         => $sanitizedNewMonthly,
            'notes'               => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
            'created_at'          => now(),
        ]);
    }
}
