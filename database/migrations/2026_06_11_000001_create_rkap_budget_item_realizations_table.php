<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rkap_budget_item_realizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rkap_budget_item_id')
                ->constrained('rkap_budget_items')
                ->cascadeOnDelete();
            $table->tinyInteger('month'); // 1–12
            $table->decimal('amount', 18, 2)->default(0);
            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->unique(['rkap_budget_item_id', 'month'], 'rkap_realization_item_month_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rkap_budget_item_realizations');
    }
};
