<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Coa;
use App\Models\CoaCategory;

class CoaProfitLossMappingSeeder extends Seeder
{
    /**
     * The 12 P&L categories.
     */
    private const CATEGORIES = [
        ['key' => 'revenue_passenger',         'label' => 'Pendapatan Tiket Penumpang',            'group' => 'Revenue',       'color' => 'success',   'sort_order' => 1],
        ['key' => 'revenue_non_passenger',     'label' => 'Pendapatan Non-Tiket / Komersial',      'group' => 'Revenue',       'color' => 'success',   'sort_order' => 2],
        ['key' => 'direct_cost_traction',      'label' => 'Beban Energi Listrik Traksi',           'group' => 'Direct Cost',   'color' => 'info',      'sort_order' => 3],
        ['key' => 'direct_cost_maintenance',   'label' => 'Beban Pemeliharaan Sarana & Prasarana',  'group' => 'Direct Cost',   'color' => 'info',      'sort_order' => 4],
        ['key' => 'direct_cost_crew',          'label' => 'Beban Awak KA & Staf Stasiun',          'group' => 'Direct Cost',   'color' => 'info',      'sort_order' => 5],
        ['key' => 'direct_cost_passenger',     'label' => 'Beban Pelayanan Penumpang',              'group' => 'Direct Cost',   'color' => 'info',      'sort_order' => 6],
        ['key' => 'direct_cost_others',        'label' => 'Beban Langsung Lainnya',                 'group' => 'Direct Cost',   'color' => 'info',      'sort_order' => 7],
        ['key' => 'indirect_cost_marketing',   'label' => 'Beban Pemasaran & Penjualan',            'group' => 'Indirect Cost', 'color' => 'warning',   'sort_order' => 8],
        ['key' => 'indirect_cost_admin',       'label' => 'Beban Umum & Administrasi',              'group' => 'Indirect Cost', 'color' => 'warning',   'sort_order' => 9],
        ['key' => 'depreciation_amortization', 'label' => 'Beban Penyusutan & Amortisasi',          'group' => 'Indirect Cost', 'color' => 'warning',   'sort_order' => 10],
        ['key' => 'non_operating_revenue',     'label' => 'Pendapatan Non-Operasional',             'group' => 'Non-Operating', 'color' => 'secondary', 'sort_order' => 11],
        ['key' => 'non_operating_expense',     'label' => 'Beban Non-Operasional / Keuangan',       'group' => 'Non-Operating', 'color' => 'secondary', 'sort_order' => 12],
    ];

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
        // 1. Seed or update coa_categories
        foreach (self::CATEGORIES as $cat) {
            CoaCategory::updateOrCreate(
                ['key' => $cat['key']],
                [
                    'label' => $cat['label'],
                    'group' => $cat['group'],
                    'color' => $cat['color'],
                    'sort_order' => $cat['sort_order'],
                ]
            );
        }
        $this->command->info("Seeded / updated Coa categories.");

        // Fetch categories to map key -> id
        $categories = CoaCategory::all()->keyBy('key');

        // Reset existing mappings
        Coa::query()->update(['coa_category_id' => null]);

        // 2. Apply prefix-based rules
        foreach (self::PREFIX_RULES as $prefix => $catKey) {
            $catId = $categories->get($catKey)?->id;
            if ($catId) {
                $count = Coa::where('code', 'like', $prefix . '%')
                    ->update(['coa_category_id' => $catId]);
                $this->command->info("  {$prefix}xx → {$catKey} (ID: {$catId}): {$count} COAs");
            }
        }

        // 3. Apply specific code overrides
        foreach (self::CODE_OVERRIDES as $code => $catKey) {
            $catId = $categories->get($catKey)?->id;
            if ($catId) {
                Coa::where('code', $code)->update(['coa_category_id' => $catId]);
            }
        }
        $this->command->info("  Applied " . count(self::CODE_OVERRIDES) . " code overrides");

        // 4. Handle 99xx by title keywords
        $stat99 = Coa::where('code', 'like', '99%')->get();
        $mapped99 = 0;
        $defaultCatId = $categories->get('direct_cost_others')?->id;

        foreach ($stat99 as $coa) {
            $titleLower = strtolower($coa->title);
            $catId = $defaultCatId;

            foreach (self::TITLE_KEYWORDS_99XX as $keyword => $catKey) {
                if (str_contains($titleLower, $keyword)) {
                    $catId = $categories->get($catKey)?->id ?? $defaultCatId;
                    break;
                }
            }

            $coa->update(['coa_category_id' => $catId]);
            $mapped99++;
        }
        $this->command->info("  99xx (by title): {$mapped99} COAs");

        // Summary
        $total = Coa::whereNotNull('coa_category_id')->count();
        $unmapped = Coa::whereNull('coa_category_id')->count();
        $this->command->newLine();
        $this->command->info("Mapped {$total} COAs to P&L categories. {$unmapped} accounts unmapped (Balance Sheet/CAPEX).");
    }
}
