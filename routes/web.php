<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Settings\PortalSettingsController;
use App\Http\Controllers\Settings\UsersController;
use App\Http\Controllers\Settings\PermissionSetsController;
use App\Http\Controllers\Settings\SmtpSettingsController;
use App\Http\Controllers\TwoFactorController;

Route::get('/', function () {
    return redirect()->route('dashboard');
})->middleware('auth');

Route::middleware(['auth', '2fa'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard')
        ->middleware('permission:view_dashboard');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::post('/profile/name', [ProfileController::class, 'updateName'])
        ->name('profile.updateName');

    Route::post('/profile/email/request', [ProfileController::class, 'requestEmailChange'])
        ->name('profile.requestEmailChange');

    Route::post('/profile/email/confirm', [ProfileController::class, 'confirmEmailChange'])
        ->name('profile.confirmEmailChange');

    Route::post('/profile/password', [ProfileController::class, 'updatePassword'])
        ->name('profile.updatePassword');

    // 2FA
    Route::post('/profile/2fa/enable', [TwoFactorController::class, 'enable'])
        ->name('profile.2fa.enable');

    Route::post('/profile/2fa/confirm', [TwoFactorController::class, 'confirm'])
        ->name('profile.2fa.confirm');

    Route::post('/profile/2fa/disable', [TwoFactorController::class, 'disable'])
        ->name('profile.2fa.disable');

    // Settings group
    Route::prefix('settings')->name('settings.')->group(function () {

        Route::get('/portal', [PortalSettingsController::class, 'edit'])
            ->name('portal.edit')
            ->middleware('permission:manage_portal_settings');

        Route::post('/portal', [PortalSettingsController::class, 'update'])
            ->name('portal.update')
            ->middleware('permission:manage_portal_settings');

        /* Users Page */
        Route::get('/users', [UsersController::class, 'index'])
            ->name('users.index')
            ->middleware('permission:manage_users');

        Route::get('/users/create', [UsersController::class, 'create'])
            ->name('users.create')
            ->middleware('permission:manage_users');

        Route::post('/users', [UsersController::class, 'store'])
            ->name('users.store')
            ->middleware('permission:manage_users');

        Route::get('/users/{user}/edit', [UsersController::class, 'edit'])
            ->name('users.edit')
            ->middleware('permission:manage_users');

        Route::put('/users/{user}', [UsersController::class, 'update'])
            ->name('users.update')
            ->middleware('permission:manage_users');

        Route::delete('/users/{user}', [UsersController::class, 'destroy'])
            ->name('users.destroy')
            ->middleware('permission:manage_users');

        /* SMTP Settings */
        Route::get('/smtp', [SmtpSettingsController::class, 'edit'])
            ->name('smtp.edit')
            ->middleware('permission:manage_smtp_settings');

        Route::put('/smtp', [SmtpSettingsController::class, 'update'])
            ->name('smtp.update')
            ->middleware('permission:manage_smtp_settings');

        Route::post('/smtp/test', [SmtpSettingsController::class, 'test'])
            ->name('smtp.test')
            ->middleware('permission:manage_smtp_settings');

        /* Permission Set Page */
        Route::get('/permission-sets', [PermissionSetsController::class, 'index'])
            ->name('permissionSets.index')
            ->middleware('permission:manage_permission_sets');

        Route::post('/permission-sets', [PermissionSetsController::class, 'storeRole'])
            ->name('permissionSets.storeRole')
            ->middleware('permission:manage_permission_sets');

        Route::post('/permission-sets/{role}/update', [PermissionSetsController::class, 'updateRolePermissions'])
            ->name('permissionSets.updateRolePermissions')
            ->middleware('permission:manage_permission_sets');

        Route::post('/permission-sets/{role}/delete', [PermissionSetsController::class, 'destroyRole'])
            ->name('permissionSets.destroyRole')
            ->middleware('permission:manage_permission_sets');
    });
});

// 2FA challenge routes (must be auth but NOT 2fa middleware)
Route::middleware('auth')->group(function () {
    Route::get('/two-factor', [TwoFactorController::class, 'challengeForm'])
        ->name('2fa.challenge');

    Route::post('/two-factor', [TwoFactorController::class, 'challengeVerify'])
        ->name('2fa.verify');
});

require __DIR__ . '/auth.php';
