<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_transfers', function (Blueprint $table) {
            $table->string('transfer_type')->default('intra_department')->after('status'); // intra_department, inter_department
            $table->foreignId('source_dept_approved_by')->nullable()->after('reviewed_by')->constrained('users')->nullOnDelete();
            $table->timestamp('source_dept_approved_at')->nullable()->after('source_dept_approved_by');
            $table->text('source_dept_review_notes')->nullable()->after('source_dept_approved_at');
            $table->foreignId('target_dept_approved_by')->nullable()->after('source_dept_review_notes')->constrained('users')->nullOnDelete();
            $table->timestamp('target_dept_approved_at')->nullable()->after('target_dept_approved_by');
            $table->text('target_dept_review_notes')->nullable()->after('target_dept_approved_at');
        });

        Schema::create('budget_transfer_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_transfer_id')->constrained('budget_transfers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('stage'); // source_department, target_department, target_bureau
            $table->string('action'); // approved, rejected
            $table->text('comments')->nullable();
            $table->timestamps();
        });

        // Ensure kepala_departemen has review and view permissions
        try {
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
            $reviewPerm = Permission::firstOrCreate(['name' => 'rkap.transfer.review', 'guard_name' => 'web']);
            $viewPerm = Permission::firstOrCreate(['name' => 'rkap.transfer.view', 'guard_name' => 'web']);

            $kadeptRole = Role::where('name', 'kepala_departemen')->first();
            if ($kadeptRole) {
                $kadeptRole->givePermissionTo([$viewPerm, $reviewPerm]);
            }
        } catch (\Throwable $e) {
            // In testing environments with mock databases, continue silently
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_transfer_approvals');

        Schema::table('budget_transfers', function (Blueprint $table) {
            $table->dropForeign(['source_dept_approved_by']);
            $table->dropForeign(['target_dept_approved_by']);
            $table->dropColumn([
                'transfer_type',
                'source_dept_approved_by',
                'source_dept_approved_at',
                'source_dept_review_notes',
                'target_dept_approved_by',
                'target_dept_approved_at',
                'target_dept_review_notes',
            ]);
        });
    }
};
