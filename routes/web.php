<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\dashboard\Analytics;
use App\Http\Controllers\layouts\WithoutMenu;
use App\Http\Controllers\layouts\WithoutNavbar;
use App\Http\Controllers\layouts\Fluid;
use App\Http\Controllers\layouts\Container;
use App\Http\Controllers\layouts\Blank;
use App\Http\Controllers\pages\AccountSettingsAccount;
use App\Http\Controllers\pages\AccountSettingsNotifications;
use App\Http\Controllers\pages\AccountSettingsConnections;
use App\Http\Controllers\pages\MiscError;
use App\Http\Controllers\pages\MiscUnderMaintenance;
use App\Http\Controllers\authentications\LoginBasic;
use App\Http\Controllers\authentications\RegisterBasic;
use App\Http\Controllers\authentications\ForgotPasswordBasic;
use App\Http\Controllers\cards\CardBasic;
use App\Http\Controllers\user_interface\Accordion;
use App\Http\Controllers\user_interface\Alerts;
use App\Http\Controllers\user_interface\Badges;
use App\Http\Controllers\user_interface\Buttons;
use App\Http\Controllers\user_interface\Carousel;
use App\Http\Controllers\user_interface\Collapse;
use App\Http\Controllers\user_interface\Dropdowns;
use App\Http\Controllers\user_interface\Footer;
use App\Http\Controllers\user_interface\ListGroups;
use App\Http\Controllers\user_interface\Modals;
use App\Http\Controllers\user_interface\Navbar;
use App\Http\Controllers\user_interface\Offcanvas;
use App\Http\Controllers\user_interface\PaginationBreadcrumbs;
use App\Http\Controllers\user_interface\Progress;
use App\Http\Controllers\user_interface\Spinners;
use App\Http\Controllers\user_interface\TabsPills;
use App\Http\Controllers\user_interface\Toasts;
use App\Http\Controllers\user_interface\TooltipsPopovers;
use App\Http\Controllers\user_interface\Typography;
use App\Http\Controllers\extended_ui\PerfectScrollbar;
use App\Http\Controllers\extended_ui\TextDivider;
use App\Http\Controllers\icons\Boxicons;
use App\Http\Controllers\form_elements\BasicInput;
use App\Http\Controllers\form_elements\InputGroups;
use App\Http\Controllers\form_layouts\VerticalForm;
use App\Http\Controllers\form_layouts\HorizontalForm;
use App\Http\Controllers\tables\Basic as TablesBasic;

// Main Page Route
Route::middleware(['auth'])->group(function () {
    Route::get('/', [Analytics::class, 'index'])->name('dashboard-analytics');
    Route::post('/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        return redirect('/login');
    })->name('logout');

    Route::middleware(['role:admin'])->group(function () {
        Route::get('/settings/user-management', \App\Livewire\Settings\UserManagement::class)->name('settings-user-management');
        Route::get('/settings/organization', \App\Livewire\Settings\OrganizationManagement::class)->name('settings-organization');
    });

    // RKAP routes
    Route::prefix('rkap')->group(function () {
        Route::get('/dashboard', \App\Livewire\Rkap\RkapDashboard::class)->name('rkap-dashboard');
        Route::get('/periods', \App\Livewire\Rkap\RkapPeriodManagement::class)
            ->middleware('permission:rkap.manage.period')
            ->name('rkap-periods');
        Route::get('/submissions', \App\Livewire\Rkap\RkapSubmissionList::class)->name('rkap-submissions');
        Route::get('/submissions/create/{periodId}', \App\Livewire\Rkap\RkapSubmissionForm::class)->name('rkap-submissions-create');
        Route::get('/submissions/{id}/edit', \App\Livewire\Rkap\RkapSubmissionForm::class)->name('rkap-submissions-edit');
        Route::get('/submissions/{id}/review', \App\Livewire\Rkap\RkapReview::class)->name('rkap-submissions-review');
        Route::get('/submissions/{id}/versions', \App\Livewire\Rkap\RkapVersionHistory::class)->name('rkap-submissions-versions');
    });

    // Master Data Group
    Route::prefix('master-data')->name('master-data.')->group(function () {
        Route::get('work-plans', \App\Livewire\MasterData\WorkPlans::class)->name('work-plans')->middleware('can:masterdata.workplan.manage');
        Route::get('activities', \App\Livewire\MasterData\Activities::class)->name('activities')->middleware('can:masterdata.activity.manage');
    });
});


// layout
Route::get('/layouts/without-menu', [WithoutMenu::class, 'index'])->name('layouts-without-menu');
Route::get('/layouts/without-navbar', [WithoutNavbar::class, 'index'])->name('layouts-without-navbar');
Route::get('/layouts/fluid', [Fluid::class, 'index'])->name('layouts-fluid');
Route::get('/layouts/container', [Container::class, 'index'])->name('layouts-container');
Route::get('/layouts/blank', [Blank::class, 'index'])->name('layouts-blank');

// pages
Route::get('/pages/account-settings-account', [AccountSettingsAccount::class, 'index'])->name('pages-account-settings-account');
Route::get('/pages/account-settings-notifications', [AccountSettingsNotifications::class, 'index'])->name('pages-account-settings-notifications');
Route::get('/pages/account-settings-connections', [AccountSettingsConnections::class, 'index'])->name('pages-account-settings-connections');
Route::get('/pages/misc-error', [MiscError::class, 'index'])->name('pages-misc-error');
Route::get('/pages/misc-under-maintenance', [MiscUnderMaintenance::class, 'index'])->name('pages-misc-under-maintenance');

// authentication
Route::get('/login', \App\Livewire\Auth\Login::class)->name('login');
Route::get('/auth/login-basic', [LoginBasic::class, 'index'])->name('auth-login-basic');
Route::get('/auth/register-basic', [RegisterBasic::class, 'index'])->name('auth-register-basic');
Route::get('/auth/forgot-password-basic', [ForgotPasswordBasic::class, 'index'])->name('auth-reset-password-basic');

// cards
Route::get('/cards/basic', [CardBasic::class, 'index'])->name('cards-basic');
