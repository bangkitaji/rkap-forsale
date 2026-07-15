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
        Schema::table('work_plans', function (Blueprint $table) {
            $table->string('title_en')->nullable()->after('title');
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->string('title_en')->nullable()->after('title');
            $table->text('description_en')->nullable()->after('description');
        });

        Schema::table('coas', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('name');
        });

        Schema::table('coa_groups', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('name');
        });

        Schema::table('report_groups', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('name');
        });

        Schema::table('cashflow_groups', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('name');
        });

        Schema::table('difference_groups', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('name');
        });

        Schema::table('directorates', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('name');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('name');
        });

        Schema::table('bureaus', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('name');
        });

        Schema::table('satuans', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('satuans', function (Blueprint $table) {
            $table->dropColumn('name_en');
        });

        Schema::table('bureaus', function (Blueprint $table) {
            $table->dropColumn('name_en');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn('name_en');
        });

        Schema::table('directorates', function (Blueprint $table) {
            $table->dropColumn('name_en');
        });

        Schema::table('difference_groups', function (Blueprint $table) {
            $table->dropColumn('name_en');
        });

        Schema::table('cashflow_groups', function (Blueprint $table) {
            $table->dropColumn('name_en');
        });

        Schema::table('report_groups', function (Blueprint $table) {
            $table->dropColumn('name_en');
        });

        Schema::table('coa_groups', function (Blueprint $table) {
            $table->dropColumn('name_en');
        });

        Schema::table('coas', function (Blueprint $table) {
            $table->dropColumn('name_en');
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn(['title_en', 'description_en']);
        });

        Schema::table('work_plans', function (Blueprint $table) {
            $table->dropColumn('title_en');
        });
    }
};
