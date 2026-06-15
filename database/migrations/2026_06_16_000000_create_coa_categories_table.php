<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The 12 P&L categories migrated from the hardcoded PHP constant.
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

    public function up(): void
    {
        // 1. Create coa_categories table
        Schema::create('coa_categories', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('group');
            $table->string('color')->default('secondary');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // 2. Seed categories
        $now = now();
        foreach (self::CATEGORIES as $cat) {
            DB::table('coa_categories')->insert(array_merge($cat, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        // 3. Add coa_category_id FK to coas
        Schema::table('coas', function (Blueprint $table) {
            $table->foreignId('coa_category_id')
                  ->nullable()
                  ->after('coa_group_id')
                  ->constrained('coa_categories')
                  ->nullOnDelete();
        });

        // 4. Migrate data: profit_loss_group string → coa_category_id FK
        $categories = DB::table('coa_categories')->get()->keyBy('key');
        foreach ($categories as $key => $cat) {
            DB::table('coas')
                ->where('profit_loss_group', $key)
                ->update(['coa_category_id' => $cat->id]);
        }

        // 5. Drop the old string column
        Schema::table('coas', function (Blueprint $table) {
            $table->dropColumn('profit_loss_group');
        });
    }

    public function down(): void
    {
        // Re-add the string column
        Schema::table('coas', function (Blueprint $table) {
            $table->string('profit_loss_group')->nullable()->after('coa_group_id');
        });

        // Migrate data back: coa_category_id → profit_loss_group string
        $categories = DB::table('coa_categories')->get();
        foreach ($categories as $cat) {
            DB::table('coas')
                ->where('coa_category_id', $cat->id)
                ->update(['profit_loss_group' => $cat->key]);
        }

        // Drop FK and column
        Schema::table('coas', function (Blueprint $table) {
            $table->dropForeign(['coa_category_id']);
            $table->dropColumn('coa_category_id');
        });

        Schema::dropIfExists('coa_categories');
    }
};
