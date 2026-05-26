<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rkap_budget_item_cash_outs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rkap_budget_item_id')->constrained('rkap_budget_items')->cascadeOnDelete();
            $table->tinyInteger('month'); // 1–12
            $table->decimal('amount', 20, 2)->default(0);
            $table->timestamps();

            $table->unique(['rkap_budget_item_id', 'month'], 'budget_item_cash_out_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rkap_budget_item_cash_outs');
    }
};
