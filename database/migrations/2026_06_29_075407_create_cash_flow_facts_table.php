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
        Schema::create('cash_flow_facts', function (Blueprint $table) {
            $table->id('fact_id');
            $table->string('item_code');
            $table->foreignId('version_id')->constrained('financial_versions', 'version_id')->onDelete('cascade');

            // 20 total digits, 4 decimal places to safely handle huge IDR values and fractions
            $table->decimal('amount', 20, 4)->default(0.0000);

            $table->timestamps();

            // Explicit foreign key mapping for the string-based PK in line items
            $table->foreign('item_code')->references('item_code')->on('cf_line_items')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_flow_facts');
    }
};
