<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('balance_sheet_opening_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rkap_period_id')->constrained('rkap_periods')->onDelete('cascade');
            $table->foreignId('coa_id')->constrained('coas')->onDelete('cascade');
            $table->decimal('amount', 18, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->unique(['rkap_period_id', 'coa_id'], 'bs_opening_unique_period_coa');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('balance_sheet_opening_balances');
    }
};
