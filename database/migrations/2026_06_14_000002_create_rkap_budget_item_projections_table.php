<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rkap_budget_item_projections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rkap_budget_item_id')->constrained('rkap_budget_items')->onDelete('cascade');
            $table->foreignId('rkap_period_id')->constrained('rkap_periods')->onDelete('cascade');
            $table->integer('month');
            $table->decimal('amount', 18, 2)->default(0.00);
            $table->foreignId('inputted_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            // Ensure unique projection per budget item per month
            $table->unique(['rkap_budget_item_id', 'month'], 'unique_item_month_proj');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rkap_budget_item_projections');
    }
};
