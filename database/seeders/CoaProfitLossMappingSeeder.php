<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Coa;

class CoaProfitLossMappingSeeder extends Seeder
{
    /**
     * COA prefix → Profit & Loss category mapping rules.
     *
     * Balance Sheet accounts (1xx, 2xx, 3xx) and CAPEX (8xx) are NOT mapped.
     * Migration/statistical accounts (98xx) are NOT mapped.
     */
    private const PREFIX_RULES = [
        // Revenue
        '41' => 'revenue_passenger',         // Passenger ticket revenue
        '43' => 'revenue_passenger',         // Supplementary transport (baggage)
        '42' => 'revenue_non_passenger',     // Property, commercial
        '44' => 'revenue_non_passenger',     // Discount revenue

        // Direct Cost
        '51' => 'direct_cost_maintenance',   // EMU/CIT/infra maintenance labor
        '52' => 'direct_cost_maintenance',   // Infrastructure maint material
        '53' => 'direct_cost_others',        // Communication fees (GSM-R)
        '55' => 'direct_cost_others',        // Ticketing admin, payment gateway
        '56' => 'direct_cost_crew',          // Service employees train/station
        '57' => 'direct_cost_passenger',     // Passenger service on train
        '58' => 'direct_cost_others',        // Construction/consultant/investment
        '59' => 'direct_cost_others',        // Operational tools/inventory

        // Depreciation & Amortization
        '54' => 'depreciation_amortization',

        // Indirect Cost
        '61' => 'indirect_cost_admin',       // General & admin
        '62' => 'indirect_cost_admin',       // Office building maintenance
        '63' => 'indirect_cost_admin',       // Insurance, office depreciation
        '64' => 'indirect_cost_admin',       // Salary & benefits
        '65' => 'indirect_cost_admin',       // Rent (land, building, equipment)
        '66' => 'indirect_cost_admin',       // Training & development
        '67' => 'indirect_cost_admin',       // IT

        // Non-Operating
        '71' => 'non_operating_revenue',     // Interest income, non-op income
        '76' => 'non_operating_expense',     // Interest expense, financial cost
        '79' => 'non_operating_expense',     // Income tax
    ];

    /**
     * Specific COA code overrides (takes precedence over prefix rules).
     */
    private const CODE_OVERRIDES = [
        '4401000001' => 'revenue_passenger',        // Ticketing Discount → passenger
        '5104000001' => 'direct_cost_traction',      // Operating Electricity Station
        '5104000002' => 'direct_cost_traction',      // Opera Electric Overhead Catenary System
        '5501000006' => 'indirect_cost_marketing',   // Sales expenses - Marketing Activities
        '5803000001' => 'indirect_cost_marketing',   // Advertising Business Expenses
    ];

    /**
     * Title keyword → category mapping for 99xx statistical accounts.
     */
    private const TITLE_KEYWORDS_99XX = [
        'maintenance'  => 'direct_cost_maintenance',
        'depreciation' => 'depreciation_amortization',
        'amortization' => 'depreciation_amortization',
        'salary'       => 'indirect_cost_admin',
        'benefit'      => 'indirect_cost_admin',
        'ticket'       => 'direct_cost_others',
        'payment'      => 'direct_cost_others',
        'agent'        => 'direct_cost_others',
        'passenger'    => 'direct_cost_passenger',
        'catering'     => 'direct_cost_passenger',
        'employee'     => 'direct_cost_crew',
        'staff'        => 'direct_cost_crew',
        'electric'     => 'direct_cost_traction',
        'traction'     => 'direct_cost_traction',
        'advertising'  => 'indirect_cost_marketing',
        'marketing'    => 'indirect_cost_marketing',
        'rent'         => 'indirect_cost_admin',
        'office'       => 'indirect_cost_admin',
        'training'     => 'indirect_cost_admin',
        'meeting'      => 'indirect_cost_admin',
        'travel'       => 'indirect_cost_admin',
        'insurance'    => 'indirect_cost_admin',
        'revenue'      => 'non_operating_revenue',
        'income'       => 'non_operating_revenue',
        'interest'     => 'non_operating_expense',
        'tax'          => 'non_operating_expense',
    ];

    public function run(): void
    {
        // Reset existing mappings
        Coa::query()->update(['profit_loss_group' => null]);

        // 1. Apply prefix-based rules
        foreach (self::PREFIX_RULES as $prefix => $category) {
            $count = Coa::where('code', 'like', $prefix . '%')
                ->update(['profit_loss_group' => $category]);
            $this->command->info("  {$prefix}xx → {$category}: {$count} COAs");
        }

        // 2. Apply specific code overrides
        foreach (self::CODE_OVERRIDES as $code => $category) {
            Coa::where('code', $code)->update(['profit_loss_group' => $category]);
        }
        $this->command->info("  Applied " . count(self::CODE_OVERRIDES) . " code overrides");

        // 3. Handle 99xx by title keywords
        $stat99 = Coa::where('code', 'like', '99%')->get();
        $mapped99 = 0;
        foreach ($stat99 as $coa) {
            $titleLower = strtolower($coa->title);
            $category = 'direct_cost_others'; // default

            foreach (self::TITLE_KEYWORDS_99XX as $keyword => $cat) {
                if (str_contains($titleLower, $keyword)) {
                    $category = $cat;
                    break;
                }
            }

            $coa->update(['profit_loss_group' => $category]);
            $mapped99++;
        }
        $this->command->info("  99xx (by title): {$mapped99} COAs");

        // Summary
        $total = Coa::whereNotNull('profit_loss_group')->count();
        $unmapped = Coa::whereNull('profit_loss_group')->count();
        $this->command->newLine();
        $this->command->info("Mapped {$total} COAs to P&L categories. {$unmapped} accounts unmapped (Balance Sheet/CAPEX).");
    }
}
