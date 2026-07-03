<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    $permWorkPlanView = Permission::firstOrCreate([
      'name' => 'masterdata.workplan.view',
      'guard_name' => 'web',
    ]);

    $permActivityView = Permission::firstOrCreate([
      'name' => 'masterdata.activity.view',
      'guard_name' => 'web',
    ]);

    foreach (Role::all() as $role) {
      $role->givePermissionTo([$permWorkPlanView, $permActivityView]);
    }
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    foreach (Role::all() as $role) {
      if ($role->hasPermissionTo('masterdata.workplan.view')) {
        $role->revokePermissionTo('masterdata.workplan.view');
      }

      if ($role->hasPermissionTo('masterdata.activity.view')) {
        $role->revokePermissionTo('masterdata.activity.view');
      }
    }

    Permission::where('name', 'masterdata.workplan.view')->where('guard_name', 'web')->delete();
    Permission::where('name', 'masterdata.activity.view')->where('guard_name', 'web')->delete();
  }
};
