<?php

namespace App\Livewire\Traits;

trait HandlesDistribution
{
    /**
     * Generic method to toggle a month.
     */
    public function toggleMonthDistribution(int $wpIdx, int $actIdx, int $biIdx, int $month, string $monthsField, string $distributionField): void
    {
        $months = $this->workPlans[$wpIdx]['activities'][$actIdx]['budget_items'][$biIdx][$monthsField] ?? [];

        if (in_array($month, $months)) {
            $months = array_values(array_diff($months, [$month]));
            unset($this->workPlans[$wpIdx]['activities'][$actIdx]['budget_items'][$biIdx][$distributionField][$month]);
        } else {
            $months[] = $month;
            sort($months);
            $this->workPlans[$wpIdx]['activities'][$actIdx]['budget_items'][$biIdx][$distributionField][$month] = 0;
        }

        $this->workPlans[$wpIdx]['activities'][$actIdx]['budget_items'][$biIdx][$monthsField] = array_values($months);
    }

    /**
     * Generic method to select all or clear months.
     */
    public function selectAllMonthsDistribution(int $wpIdx, int $actIdx, int $biIdx, string $monthsField, string $distributionField): void
    {
        $months = $this->workPlans[$wpIdx]['activities'][$actIdx]['budget_items'][$biIdx][$monthsField] ?? [];
        if (count($months) === 12) {
            $this->workPlans[$wpIdx]['activities'][$actIdx]['budget_items'][$biIdx][$monthsField] = [];
            $this->workPlans[$wpIdx]['activities'][$actIdx]['budget_items'][$biIdx][$distributionField] = [];
        } else {
            $allMonths = range(1, 12);
            $this->workPlans[$wpIdx]['activities'][$actIdx]['budget_items'][$biIdx][$monthsField] = $allMonths;
            $distribution = $this->workPlans[$wpIdx]['activities'][$actIdx]['budget_items'][$biIdx][$distributionField] ?? [];
            foreach ($allMonths as $month) {
                if (!isset($distribution[$month])) {
                    $distribution[$month] = 0;
                }
            }
            $this->workPlans[$wpIdx]['activities'][$actIdx]['budget_items'][$biIdx][$distributionField] = $distribution;
        }
    }

    /**
     * Generic method to distribute total price evenly across selected months.
     */
    public function distributeEvenlyDistribution(int $wpIdx, int $actIdx, int $biIdx, string $monthsField, string $distributionField): void
    {
        $bi = $this->workPlans[$wpIdx]['activities'][$actIdx]['budget_items'][$biIdx];
        $qty2 = (!empty($bi['unit_2'])) ? (float) ($bi['quantity_2'] ?? 1) : 1;
        $total = (float) ($bi['quantity'] ?? 0) * $qty2 * (float) ($bi['unit_price'] ?? 0);
        $months = $bi[$monthsField] ?? [];

        if (empty($months) || $total <= 0) {
            return;
        }

        $count = count($months);
        $perMonth = floor($total / $count);
        $remainder = $total - ($perMonth * $count);

        $distribution = [];
        foreach ($months as $i => $month) {
            $distribution[$month] = ($i === $count - 1) ? $perMonth + $remainder : $perMonth;
        }

        $this->workPlans[$wpIdx]['activities'][$actIdx]['budget_items'][$biIdx][$distributionField] = $distribution;
    }

    /**
     * Generic method to get remainder of distribution vs total item price.
     */
    public function getRemainderDistribution(int $wpIdx, int $actIdx, int $biIdx, string $distributionField): float
    {
        $bi = $this->workPlans[$wpIdx]['activities'][$actIdx]['budget_items'][$biIdx] ?? null;
        if (!$bi) {
            return 0;
        }

        $qty2 = (!empty($bi['unit_2'])) ? (float) ($bi['quantity_2'] ?? 1) : 1;
        $total = (float) ($bi['quantity'] ?? 0) * $qty2 * (float) ($bi['unit_price'] ?? 0);
        $allocated = array_sum($bi[$distributionField] ?? []);

        return $total - $allocated;
    }
}
