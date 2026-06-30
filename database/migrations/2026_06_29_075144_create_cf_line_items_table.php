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
        Schema::create('cf_line_items', function (Blueprint $table) {
            $table->string('item_code')->primary(); // Using alphanumeric code as PK (e.g., CF0A1B)
            $table->foreignId('category_id')->constrained('cf_categories', 'category_id')->onDelete('cascade');
            $table->string('description');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cf_line_items');
    }
};
