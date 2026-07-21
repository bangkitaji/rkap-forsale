<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('difference_group_coa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coa_id')->constrained('coas')->onDelete('cascade');
            $table->foreignId('difference_group_id')->constrained('difference_groups')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['coa_id', 'difference_group_id'], 'diff_group_coa_unique');
        });

        // Migrate existing mappings
        $existing = DB::table('coas')
            ->whereNotNull('difference_group_id')
            ->select('id', 'difference_group_id')
            ->get();

        foreach ($existing as $row) {
            DB::table('difference_group_coa')->insert([
                'coa_id' => $row->id,
                'difference_group_id' => $row->difference_group_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Drop the foreign key and column from the coas table
        Schema::table('coas', function (Blueprint $table) {
            $table->dropForeign(['difference_group_id']);
            $table->dropColumn('difference_group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coas', function (Blueprint $table) {
            $table->foreignId('difference_group_id')->nullable()->after('cashflow_group_id')->constrained('difference_groups')->nullOnDelete();
        });

        // Restore mappings from pivot table
        $existing = DB::table('difference_group_coa')->get();
        foreach ($existing as $row) {
            DB::table('coas')
                ->where('id', $row->coa_id)
                ->update(['difference_group_id' => $row->difference_group_id]);
        }

        Schema::dropIfExists('difference_group_coa');
    }
};
