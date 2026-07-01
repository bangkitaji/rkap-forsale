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

        // ── 1. Find or create the RKAP financial_version for this period year ────
        $version = FinancialVersion::firstOrCreate(
            ['name' => 'RKAP', 'year' => $period->year],
            ['name' => 'RKAP', 'year' => $period->year]
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

        // ── 4. Accumulate total_price from rkap_budget_items (all statuses) ───────
        $accumulated = DB::table('rkap_budget_items')
            ->join('rkap_work_plans', 'rkap_budget_items.rkap_work_plan_id', '=', 'rkap_work_plans.id')
            ->join('rkap_submissions', 'rkap_work_plans.rkap_submission_id', '=', 'rkap_submissions.id')
            ->join('coas', 'rkap_budget_items.account_code', '=', 'coas.code')
            ->where('rkap_submissions.rkap_period_id', $period->id)
            ->whereNull('coas.deleted_at')
            ->whereIn('coas.cashflow_group_id', $groupIds)
            ->selectRaw('coas.cashflow_group_id, SUM(rkap_budget_items.total_price) as total')
            ->groupBy('coas.cashflow_group_id')
            ->pluck('total', 'cashflow_group_id')
            ->toArray();

        // ── 5. Upsert into cash_flow_facts ────────────────────────────────────────
        $now = now();
        $upsertedCount = 0;
        $skippedCount  = 0;

        foreach ($cashflowGroupMap as $groupId => $itemCode) {
            if (!isset($operasiItems[$itemCode])) {
                $skippedCount++;
                continue;
            }

            $rawAmount = (float) ($accumulated[$groupId] ?? 0.0);

            // Negate for outflow items (budget items entered as positive, but
            // in the cashflow statement outflows are shown as negatives)
            if (in_array($itemCode, self::OUTFLOW_ITEM_CODES)) {
                $rawAmount = -$rawAmount;
            }

            // Upsert: update if fact exists for this (item_code, version_id) pair
            $existing = DB::table('cash_flow_facts')
                ->where('item_code', $itemCode)
                ->where('version_id', $version->version_id)
                ->first();

            if ($existing) {
                DB::table('cash_flow_facts')
                    ->where('fact_id', $existing->fact_id)
                    ->update([
                        'amount'     => $rawAmount,
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table('cash_flow_facts')->insert([
                    'item_code'  => $itemCode,
                    'version_id' => $version->version_id,
                    'amount'     => $rawAmount,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $upsertedCount++;
        }

        return response()->json([
            'success'    => true,
            'message'    => "Sinkronisasi berhasil. {$upsertedCount} item arus kas operasi diperbarui untuk versi \"{$version->name} {$version->year}\".",
            'version_id' => $version->version_id,
            'version'    => "{$version->name} {$version->year}",
            'period'     => $period->title ?? "Periode {$period->year}",
            'upserted'   => $upsertedCount,
            'skipped'    => $skippedCount,
        ]);
    }
}
