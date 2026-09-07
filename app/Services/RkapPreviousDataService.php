<?php

namespace App\Services;

use App\Models\RkapSubmission;
use Illuminate\Support\Facades\DB;

class RkapPreviousDataService
{
    protected array $submissionsCache = [];
    protected array $mapsCache = [];

    /**
     * Get the previous approved submission for a bureau.
     */
    public function getPreviousApprovedSubmission(int $bureauId, int $currentPeriodYear, ?int $excludeSubmissionId = null): ?RkapSubmission
    {
        $cacheKey = "{$bureauId}-{$currentPeriodYear}-" . ($excludeSubmissionId ?? 'null');

        if (array_key_exists($cacheKey, $this->submissionsCache)) {
            return $this->submissionsCache[$cacheKey];
        }

        $query = RkapSubmission::with([
            'workPlans.budgetItems.realizations',
            'workPlans.budgetItems.projections',
            'period',
        ])
        ->where('bureau_id', $bureauId)
        ->where('status', 'approved')
        ->whereHas('period', fn($q) => $q->where('year', '<', $currentPeriodYear));

        if ($excludeSubmissionId) {
            $query->where('id', '!=', $excludeSubmissionId);
        }

        $prevSubmission = $query
            ->orderByDesc(DB::raw('(SELECT year FROM rkap_periods WHERE rkap_periods.id = rkap_submissions.rkap_period_id)'))
            ->first();

        $this->submissionsCache[$cacheKey] = $prevSubmission;

        return $prevSubmission;
    }

    /**
     * Build the previous map from a given submission.
     */
    public function buildPreviousMap(?RkapSubmission $prevSubmission): array
    {
        if (!$prevSubmission) {
            return [];
        }

        $cacheKey = $prevSubmission->id;
        if (array_key_exists($cacheKey, $this->mapsCache)) {
            return $this->mapsCache[$cacheKey];
        }

        $programs = [];
        $activities = [];
        $coas = [];
        $itemsMap = [];
        $itemCounters = [];
        $totalBudget = 0.0;
        $totalRealization = 0.0;
        $totalProjection = 0.0;

        foreach ($prevSubmission->workPlans as $wp) {
            $wpId = $wp->work_plan_id;
            $actId = $wp->activity_id;
            if (!$wpId) {
                continue;
            }

            if (!isset($programs[$wpId])) {
                $programs[$wpId] = ['budget' => 0.0, 'realization' => 0.0, 'projection' => 0.0];
            }

            $actKey = "{$wpId}-{$actId}";
            if (!isset($activities[$actKey])) {
                $activities[$actKey] = ['budget' => 0.0, 'realization' => 0.0, 'projection' => 0.0];
            }

            foreach ($wp->budgetItems as $bi) {
                $code = $bi->account_code;
                $budgetVal = (float) $bi->total_price;
                $realizationVal = (float) $bi->realizations->sum('amount');
                $projectionVal = (float) $bi->projection;

                $isGain = isset($bi->is_gain) ? filter_var($bi->is_gain, FILTER_VALIDATE_BOOLEAN) : true;
                if (\App\Models\Coa::isForexAccount($code) && $isGain) {
                    $budgetVal = -$budgetVal;
                    $realizationVal = -$realizationVal;
                    $projectionVal = -$projectionVal;
                }

                $programs[$wpId]['budget'] += $budgetVal;
                $programs[$wpId]['realization'] += $realizationVal;
                $programs[$wpId]['projection'] += $projectionVal;

                $activities[$actKey]['budget'] += $budgetVal;
                $activities[$actKey]['realization'] += $realizationVal;
                $activities[$actKey]['projection'] += $projectionVal;

                $totalBudget += $budgetVal;
                $totalRealization += $realizationVal;
                $totalProjection += $projectionVal;

                if ($code) {
                    $coaKey = "{$wpId}-{$actId}-{$code}";
                    if (!isset($coas[$coaKey])) {
                        $coas[$coaKey] = ['budget' => 0.0, 'realization' => 0.0, 'projection' => 0.0];
                      }
                      $coas[$coaKey]['budget'] += $budgetVal;
                      $coas[$coaKey]['realization'] += $realizationVal;
                      $coas[$coaKey]['projection'] += $projectionVal;

                      $baseKey = "{$wpId}-{$actId}-{$code}-" . trim(strtolower($bi->description));
                      $itemCounters[$baseKey] = ($itemCounters[$baseKey] ?? 0) + 1;
                      $itemKey = "{$baseKey}-" . $itemCounters[$baseKey];

                      $itemsMap[$itemKey] = [
                          'budget' => $budgetVal,
                          'realization' => $realizationVal,
                          'projection' => $projectionVal,
                      ];
                }
            }
        }

        $result = [
            'map' => [
                'programs' => $programs,
                'activities' => $activities,
                'coas' => $coas,
                'items' => $itemsMap,
            ],
            'period' => $prevSubmission->period?->title ?? '-',
            'total_budget' => $totalBudget,
            'total_realization' => $totalRealization,
            'total_projection' => $totalProjection,
        ];

        $this->mapsCache[$cacheKey] = $result;

        return $result;
    }

    /**
     * Batch retrieve previous submission data for multiple submissions to fix N+1.
     */
    public function getBatchPreviousData(iterable $submissions): array
    {
        $results = [];
        foreach ($submissions as $sub) {
            $bureauId = $sub->bureau_id;
            $year = $sub->period?->year ?? 0;
            if (!$bureauId || !$year) {
                continue;
            }

            $prevSubmission = $this->getPreviousApprovedSubmission($bureauId, $year, $sub->id);
            if ($prevSubmission) {
                $mapData = $this->buildPreviousMap($prevSubmission);
                $results[$sub->id] = [
                    'period_title' => $prevSubmission->period->title,
                    'budget' => (float) $prevSubmission->total_budget,
                    'realization' => $mapData['total_realization'],
                    'projection' => $mapData['total_projection'],
                ];
            }
        }
        return $results;
    }
}
