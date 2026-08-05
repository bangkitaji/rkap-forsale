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
        Schema::create('rkap_projection_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rkap_budget_item_id')->constrained('rkap_budget_items')->onDelete('cascade');
            $table->foreignId('rkap_period_id')->constrained('rkap_periods')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('batch_id')->nullable()->index();
            $table->string('source', 30)->default('manual'); // 'manual' or 'bulk_upload'
            $table->string('input_mode', 20)->default('monthly'); // 'monthly' or 'yearly'
            $table->decimal('old_total', 15, 2)->default(0);
            $table->decimal('new_total', 15, 2)->default(0);
            $table->json('old_monthly')->nullable();
            $table->json('new_monthly')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rkap_projection_logs');
    }
};
