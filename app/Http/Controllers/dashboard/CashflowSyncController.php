<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use App\Models\CfLineItem;
use App\Models\CfCategory;
use App\Models\CashflowGroup;
use App\Models\FinancialVersion;
use App\Models\RkapPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CashflowSyncController extends Controller
{
    /**
     * Item codes in Category 1 (Arus Kas Aktivitas Operasi) that represent
     * outflows. Their total_price from RKAP budget items must be negated
     * because budget items are entered as positive values.
     */
    protected const OUTFLOW_ITEM_CODES = [
        'CF0B2',  // Pembayaran Pemasok
        'CF0B3',  // Pembayaran ke Karyawan
        'CF0B5',  // Pembayaran bunga
        'CF0B2A', // Pembayaran ke Pemasok (non opex)
    ];

    /**
     * Sync cash_flow_facts for Arus Kas Aktivitas Operasi (category_id = 1)
     * from RKAP proposal data (all submission statuses) for the selected period.
     *
     * Method: accumulate total_price grouped by cashflow_group_id (= item_code),
     * then upsert into cash_flow_facts. Uses/creates a FinancialVersion with
     * name='RKAP' and year = period.year.
     */
    public function sync(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            abort(403);
        }

        $validated = $request->validate([
            'period_id' => ['required', 'integer', 'exists:rkap_periods,id'],
        ]);

        $period = RkapPeriod::findOrFail($validated['period_id']);

        // ── 1. Find or create the RKAP and PROGNOSA financial_versions for this period year ────
        $version = FinancialVersion::firstOrCreate(
            ['name' => 'RKAP', 'year' => $period->year],
            ['name' => 'RKAP', 'year' => $period->year]
        );

        $prognosaVersion = FinancialVersion::firstOrCreate(
            ['name' => 'PROGNOSA', 'year' => $period->year],
            ['name' => 'PROGNOSA', 'year' => $period->year]
        );

        // ── 2. Get all cf_line_items that belong to category 1 (Operasi) ─────────
        $operasiCategory = CfCategory::where('category_id', 1)->first();
        if (!$operasiCategory) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori Arus Kas Aktivitas Operasi tidak ditemukan.',
            ], 422);
        }

        /** @var \Illuminate\Support\Collection<int, \App\Models\CfLineItem> */
        $operasiItems = CfLineItem::where('category_id', 1)->get()->keyBy('item_code');

        if ($operasiItems->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada line items untuk Arus Kas Aktivitas Operasi.',
            ], 422);
        }

        // Build the list of cashflow_group codes that map to operasi line items
        $operasiItemCodes = $operasiItems->keys()->toArray();

        // ── 3. Get cashflow_group IDs for operasi item codes ──────────────────────
        // cashflow_groups.code === cf_line_items.item_code (same codes, different tables)
        $cashflowGroupMap = CashflowGroup::whereIn('code', $operasiItemCodes)
            ->pluck('code', 'id'); // [group_id => code]

        if ($cashflowGroupMap->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada cashflow group yang sesuai dengan line items operasi.',
            ], 422);
        }

        $groupIds = $cashflowGroupMap->keys()->toArray();

        $cf0b9GroupId = array_search('CF0B9', $cashflowGroupMap->toArray());
 
        // ── 4a. Accumulate RKAP Rencana Kas Keluar totals ─────────────────────────
        $accumulatedRkap = DB::table('rkap_budget_items')
            ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
            ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
            ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
            ->leftJoin(
                DB::raw('(SELECT rkap_budget_item_id, SUM(amount) as cash_out_total FROM rkap_budget_item_cash_outs GROUP BY rkap_budget_item_id) as co'),
                'co.rkap_budget_item_id', '=', 'rkap_budget_items.id'
            )
            ->where('rkap_submissions.rkap_period_id', $period->id)
            ->whereNull('coas.deleted_at')
            ->whereIn('coas.cashflow_group_id', $groupIds)
            ->selectRaw(
                $cf0b9GroupId !== false
                ? "coas.cashflow_group_id, SUM(CASE WHEN coas.cashflow_group_id = {$cf0b9GroupId} THEN (CASE WHEN rkap_budget_items.flow_direction = 'OUT' THEN -COALESCE(co.cash_out_total, rkap_budget_items.total_price) ELSE COALESCE(co.cash_out_total, rkap_budget_items.total_price) END) ELSE COALESCE(co.cash_out_total, rkap_budget_items.total_price) END) as total"
                : "coas.cashflow_group_id, SUM(COALESCE(co.cash_out_total, rkap_budget_items.total_price)) as total"
            )
            ->groupBy('coas.cashflow_group_id')
            ->pluck('total', 'cashflow_group_id')
            ->toArray();

        // ── 4b. Accumulate PROGNOSA Projection totals (projection) ────────────────
        $accumulatedPrognosa = DB::table('rkap_budget_items')
            ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
            ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
            ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
            ->where('rkap_submissions.rkap_period_id', $period->id)
            ->whereNull('coas.deleted_at')
            ->whereIn('coas.cashflow_group_id', $groupIds)
            ->selectRaw(
                $cf0b9GroupId !== false
                ? "coas.cashflow_group_id, SUM(CASE WHEN coas.cashflow_group_id = {$cf0b9GroupId} THEN (CASE WHEN rkap_budget_items.flow_direction = 'OUT' THEN -rkap_budget_items.projection ELSE rkap_budget_items.projection END) ELSE rkap_budget_items.projection END) as total"
                : "coas.cashflow_group_id, SUM(rkap_budget_items.projection) as total"
            )
            ->groupBy('coas.cashflow_group_id')
            ->pluck('total', 'cashflow_group_id')
            ->toArray();

        // ── 5. Upsert into cash_flow_facts for both versions ─────────────────────
        $now = now();
        $upsertedCount = 0;
        $skippedCount  = 0;

        foreach ($cashflowGroupMap as $groupId => $itemCode) {
            if (!isset($operasiItems[$itemCode])) {
                $skippedCount++;
                continue;
            }

            // --- RKAP Version Upsert ---
            $rawAmountRkap = (float) ($accumulatedRkap[$groupId] ?? 0.0);
            if (in_array($itemCode, self::OUTFLOW_ITEM_CODES)) {
                $rawAmountRkap = -$rawAmountRkap;
            }

            $existingRkap = DB::table('cash_flow_facts')
                ->where('item_code', $itemCode)
                ->where('version_id', $version->version_id)
                ->first();

            if ($existingRkap) {
                DB::table('cash_flow_facts')
                    ->where('fact_id', $existingRkap->fact_id)
                    ->update([
                        'amount'     => $rawAmountRkap,
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table('cash_flow_facts')->insert([
                    'item_code'  => $itemCode,
                    'version_id' => $version->version_id,
                    'amount'     => $rawAmountRkap,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // --- PROGNOSA Version Upsert ---
            $rawAmountPrognosa = (float) ($accumulatedPrognosa[$groupId] ?? 0.0);
            if (in_array($itemCode, self::OUTFLOW_ITEM_CODES)) {
                $rawAmountPrognosa = -$rawAmountPrognosa;
            }

            $existingPrognosa = DB::table('cash_flow_facts')
                ->where('item_code', $itemCode)
                ->where('version_id', $prognosaVersion->version_id)
                ->first();

            if ($existingPrognosa) {
                DB::table('cash_flow_facts')
                    ->where('fact_id', $existingPrognosa->fact_id)
                    ->update([
                        'amount'     => $rawAmountPrognosa,
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table('cash_flow_facts')->insert([
                    'item_code'  => $itemCode,
                    'version_id' => $prognosaVersion->version_id,
                    'amount'     => $rawAmountPrognosa,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $upsertedCount++;
        }

        return response()->json([
            'success'    => true,
            'message'    => "Sinkronisasi berhasil. {$upsertedCount} item arus kas operasi diperbarui untuk versi \"{$version->name} {$version->year}\" dan \"{$prognosaVersion->name} {$prognosaVersion->year}\".",
            'version_id' => $version->version_id,
            'version'    => "{$version->name} {$version->year}",
            'period'     => $period->title ?? "Periode {$period->year}",
            'upserted'   => $upsertedCount,
            'skipped'    => $skippedCount,
        ]);
    }
}
