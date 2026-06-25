<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\dashboard\Analytics;
use App\Http\Controllers\authentications\LoginBasic;
use App\Http\Controllers\authentications\RegisterBasic;
use App\Http\Controllers\authentications\ForgotPasswordBasic;

// Main Page Route
Route::middleware(['auth'])->group(function () {
  Route::get('/analytics', [Analytics::class, 'index'])->name('dashboard-analytics');
  Route::get('/analytics/coa-group-detail', [Analytics::class, 'coaGroupDetail'])->name('analytics.coa-group-detail');
  Route::get('/', \App\Livewire\Rkap\RkapDashboard::class)->name('rkap-dashboard');
  Route::get('/my-profile/{tab?}', \App\Livewire\Auth\MyProfile::class)->name('my-profile');
  Route::get('/change-password', function () {
    return redirect()->route('my-profile', ['tab' => 'security']);
  })->name('change-password');

  Route::post('/logout', function () {
    \Illuminate\Support\Facades\Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/login');
  })->name('logout');

  Route::middleware(['role:admin'])->group(function () {
    // User management (split into separate routes)
    Route::get('/settings/user-management', \App\Livewire\Settings\UserManagementUsers::class)->name('settings-user-management');
    Route::get('/settings/user-management/users', \App\Livewire\Settings\UserManagementUsers::class)->name('settings-user-management-users');
    Route::get('/settings/user-management/roles', \App\Livewire\Settings\UserManagementRoles::class)->name('settings-user-management-roles');
    Route::get('/settings/user-management/permissions', \App\Livewire\Settings\UserManagementPermissions::class)->name('settings-user-management-permissions');

    Route::get('/settings/organization', \App\Livewire\Settings\OrganizationDirectorates::class)->name('settings-organization');
    Route::get('/settings/organization/directorates', \App\Livewire\Settings\OrganizationDirectorates::class)->name('settings-organization-directorates');
    Route::get('/settings/organization/departments', \App\Livewire\Settings\OrganizationDepartments::class)->name('settings-organization-departments');
    Route::get('/settings/organization/bureaus', \App\Livewire\Settings\OrganizationBureaus::class)->name('settings-organization-bureaus');

    Route::get('/settings/satuans', \App\Livewire\Settings\Satuans::class)
      ->name('settings-satuans')
      ->middleware('permission:settings.satuan.manage');

    Route::get('/settings/data-migration-upload', \App\Livewire\Settings\DataMigrationUpload::class)
      ->name('settings-data-migration-upload')
      ->middleware('permission:settings.show');
  });

  Route::get('/settings/report-groups', \App\Livewire\Settings\ReportGroups::class)
    ->name('settings-report-groups')
    ->middleware('permission:settings.reportgroup.manage');

  Route::get('/settings/cashflow-groups', \App\Livewire\Settings\CashflowGroups::class)
    ->name('settings-cashflow-groups')
    ->middleware('permission:settings.cashflowgroup.manage');

  // RKAP routes
  Route::prefix('rkap')->group(function () {
    Route::get('/dashboard-rkap', \App\Livewire\Rkap\RkapDashboard::class)->name('dashboard-rkap');
    Route::get('/periods', \App\Livewire\Rkap\RkapPeriodManagement::class)
      ->middleware('permission:rkap.manage.period')
      ->name('rkap-periods');
    Route::get('/submissions', \App\Livewire\Rkap\RkapSubmissionList::class)->name('rkap-submissions');
    Route::get('/requests', \App\Livewire\Rkap\RkapRequests::class)->name('rkap-requests');
    Route::get('/submissions/create/{periodId}', \App\Livewire\Rkap\RkapSubmissionForm::class)->name('rkap-submissions-create');
    Route::get('/submissions/{id}/edit', \App\Livewire\Rkap\RkapSubmissionForm::class)->name('rkap-submissions-edit');
    Route::get('/submissions/{id}/review', \App\Livewire\Rkap\RkapReview::class)->name('rkap-submissions-review');
    Route::get('/submissions/{id}/approval-review', \App\Livewire\Rkap\RkapApprovalReview::class)->name('rkap-submissions-approval-review');
    Route::get('/submissions/{id}/versions', \App\Livewire\Rkap\RkapVersionHistory::class)->name('rkap-submissions-versions');
    Route::get('/compilation', \App\Livewire\Rkap\RkapSubmissionCompilation::class)
      ->middleware('permission:rkap.compilation.dept')
      ->name('rkap-submissions-compilation');
    Route::get('/realization-upload', \App\Livewire\Rkap\RkapRealizationUpload::class)
      ->middleware('permission:rkap.realization.upload')
      ->name('rkap-realization-upload');
    Route::get('/projections', \App\Livewire\Rkap\RkapProjections::class)
      ->middleware('permission:rkap.projection.view')
      ->name('rkap-projections');
    Route::get('/projections/upload', \App\Livewire\Rkap\RkapProjectionUpload::class)
      ->middleware('permission:rkap.projection.input')
      ->name('rkap-projection-upload');
    Route::get('/projection-template/download', function () {
      $periodId = request('period_id') ? (int) request('period_id') : null;
      $filename = 'template_upload_projection_' . now()->format('YmdHis') . '.xlsx';
      return \Maatwebsite\Excel\Facades\Excel::download(
        new \App\Exports\RkapProjectionTemplateExport($periodId),
        $filename
      );
    })
      ->middleware('permission:rkap.projection.input')
      ->name('rkap-projection-template-download');
    Route::get('/realization-template/download', function () {
      \Illuminate\Support\Facades\Log::info('Realization template route hit', [
        'period_id' => request('period_id'),
        'month' => request('month'),
      ]);
      $periodId = request('period_id') ? (int) request('period_id') : null;
      $month = request('month') ? (int) request('month') : null;
      $filename = 'template_upload_realization_' . now()->format('YmdHis') . '.xlsx';
      return \Maatwebsite\Excel\Facades\Excel::download(
        new \App\Exports\RkapRealizationTemplateExport($periodId, $month),
        $filename
      );
    })
      ->middleware('permission:rkap.realization.upload')
      ->name('rkap-realization-template-download');

    Route::get('/realization-template/download-csv', function () {
      $periodId = request('period_id') ? (int) request('period_id') : null;
      $month = request('month') ? (int) request('month') : null;
      $filename = 'template_upload_realization_' . now()->format('YmdHis') . '.csv';
      return \Maatwebsite\Excel\Facades\Excel::download(
        new \App\Exports\RkapRealizationTemplateExport($periodId, $month),
        $filename,
        \Maatwebsite\Excel\Excel::CSV
      );
    })
      ->middleware('permission:rkap.realization.upload')
      ->name('rkap-realization-template-download-csv');
  });

  // Master Data Group
  Route::prefix('master-data')->name('master-data.')->group(function () {
    Route::get('work-plans', \App\Livewire\MasterData\WorkPlans::class)->name('work-plans')->middleware('can:masterdata.workplan.manage');
    Route::get('activities', \App\Livewire\MasterData\Activities::class)->name('activities')->middleware('can:masterdata.activity.manage');
    Route::get('coas', \App\Livewire\MasterData\Coas::class)->name('coa.coas')->middleware('can:masterdata.coa.manage');
    Route::get('coa-groups', \App\Livewire\MasterData\CoaGroups::class)->name('coa.coa-groups')->middleware('can:masterdata.coagroup.manage');
    Route::get('coa-profit-loss-mapping', \App\Livewire\MasterData\CoaProfitLossMapping::class)->name('coa-profit-loss-mapping')->middleware('can:masterdata.coaprofitloss.manage');
    Route::get('activity-coa-mapping', \App\Livewire\MasterData\ActivityCoaMapping::class)->name('coa.activity-coa-mapping')->middleware('can:masterdata.activity.manage');
  });

  // Template downloads
  Route::get('templates/download/workplan', function () {
    return response()->download(
      public_path('templates/workplan_template.xlsx'),
      'workplan_template.xlsx'
    );
  })->name('download-workplan-template')->middleware('can:masterdata.workplan.manage');

  Route::get('templates/download/activity', function () {
    return response()->download(
      public_path('templates/activity_template.xlsx'),
      'activity_template.xlsx'
    );
  })->name('download-activity-template')->middleware('can:masterdata.activity.manage');

  Route::get('templates/download/coa', function () {
    return response()->download(
      public_path('templates/coa_template.xlsx'),
      'coa_template.xlsx'
    );
  })->name('download-coa-template')->middleware('can:masterdata.coa.manage');

  Route::get('templates/download/activity-coa-mapping', function () {
    return response()->download(
      public_path('templates/activity_coa_mapping_template.xlsx'),
      'activity_coa_mapping_template.xlsx'
    );
  })->name('download-activity-coa-mapping-template')->middleware('can:masterdata.activity.manage');
});


// authentication
Route::get('/login', \App\Livewire\Auth\Login::class)->name('login');
Route::get('/auth/login-basic', [LoginBasic::class, 'index'])->name('auth-login-basic');
Route::get('/auth/register-basic', [RegisterBasic::class, 'index'])->name('auth-register-basic');
Route::get('/auth/forgot-password-basic', [ForgotPasswordBasic::class, 'index'])->name('auth-reset-password-basic');
