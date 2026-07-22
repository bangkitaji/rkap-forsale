<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_transfer_items', function (Blueprint $table) {
            $table->foreignId('rkap_budget_item_id')->nullable()->after('rkap_work_plan_id')->constrained('rkap_budget_items')->cascadeOnDelete();
            $table->decimal('amount_transferred', 20, 2)->default(0)->after('rkap_budget_item_id');
            $table->json('monthly_distribution')->nullable()->after('amount_transferred');
        });
    }

    public function down(): void
    {
        Schema::table('budget_transfer_items', function (Blueprint $table) {
            $table->dropForeign(['rkap_budget_item_id']);
            $table->dropColumn(['rkap_budget_item_id', 'amount_transferred', 'monthly_distribution']);
        });
    }
};
