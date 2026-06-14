<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE rkap_submissions DROP CONSTRAINT IF EXISTS rkap_submissions_status_check');
            DB::statement("ALTER TABLE rkap_submissions ADD CONSTRAINT rkap_submissions_status_check CHECK (status IN ('draft', 'submitted', 'dept_review', 'dept_approved', 'dept_revision', 'dir_review', 'dir_approved', 'dir_revision', 'final_review', 'final_revision', 'verifikator_approved', 'pdir_review', 'pdir_revision', 'approved'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE rkap_submissions DROP CONSTRAINT IF EXISTS rkap_submissions_status_check');
            DB::statement("ALTER TABLE rkap_submissions ADD CONSTRAINT rkap_submissions_status_check CHECK (status IN ('draft', 'submitted', 'dept_review', 'dept_approved', 'dept_revision', 'dir_review', 'dir_approved', 'dir_revision', 'final_review', 'final_revision', 'approved'))");
        }
    }
};
