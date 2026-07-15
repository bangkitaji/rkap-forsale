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
        Schema::table('coas', function (Blueprint $table) {
            // Drop name_en column if it exists from previous migration
            if (Schema::hasColumn('coas', 'name_en')) {
                $table->dropColumn('name_en');
            }
            // Add title_en and description_en
            $table->string('title_en')->nullable()->after('title');
            $table->text('description_en')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coas', function (Blueprint $table) {
            $table->dropColumn(['title_en', 'description_en']);
            $table->string('name_en')->nullable();
        });
    }
};
