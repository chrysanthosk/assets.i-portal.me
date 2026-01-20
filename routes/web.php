<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Settings\PortalSettingsController;
use App\Http\Controllers\Settings\UsersController;
use App\Http\Controllers\Settings\PermissionSetsController;
use App\Http\Controllers\Settings\SmtpSettingsController;
use App\Http\Controllers\Settings\AssetTypesController;
use App\Http\Controllers\Settings\OwnerEntitiesController;
use App\Http\Controllers\TwoFactorController;

use App\Http\Controllers\AssetsController;
use App\Http\Controllers\AssetRentalsController;
use App\Http\Controllers\AssetTagsController;

Route::get('/__whoami', function () {
    return response()->json([
        'project' => base_path(),
        'time' => now()->toDateTimeString(),
    ]);
});

Route::get('/', function () {
    return redirect()->route('dashboard');
})->middleware('auth');

Route::middleware(['auth', '2fa'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard')
        ->middleware('permission:view_dashboard');

    // --------------------
    // Assets Management
    // --------------------
    Route::prefix('assets')->name('assets.')->group(function () {

        // Assets CRUD
        Route::get('/', [AssetsController::class, 'index'])
            ->name('index')
            ->middleware('permission:manage_assets');

        Route::get('/create', [AssetsController::class, 'create'])
            ->name('create')
            ->middleware('permission:manage_assets');

        Route::post('/', [AssetsController::class, 'store'])
            ->name('store')
            ->middleware('permission:manage_assets');

        // Rentals
        Route::get('/rentals', [AssetRentalsController::class, 'index'])
            ->name('rentals.index')
            ->middleware('permission:manage_asset_rentals');

        Route::post('/rentals', [AssetRentalsController::class, 'storeOrUpdate'])
            ->name('rentals.storeOrUpdate')
            ->middleware('permission:manage_asset_rentals');

        Route::get('/rentals/{rental}/edit', [AssetRentalsController::class, 'edit'])
            ->name('rentals.edit')
            ->middleware('permission:manage_asset_rentals');

        Route::put('/rentals/{rental}', [AssetRentalsController::class, 'update'])
            ->name('rentals.update')
            ->middleware('permission:manage_asset_rentals');

        Route::delete('/rentals/{rental}', [AssetRentalsController::class, 'destroy'])
            ->name('rentals.destroy')
            ->middleware('permission:manage_asset_rentals');

        // Tags
        Route::get('/tags', [AssetTagsController::class, 'index'])
            ->name('tags.index')
            ->middleware('permission:manage_asset_tags');

        Route::post('/tags', [AssetTagsController::class, 'store'])
            ->name('tags.store')
            ->middleware('permission:manage_asset_tags');

        Route::put('/tags/{tag}', [AssetTagsController::class, 'update'])
            ->name('tags.update')
            ->middleware('permission:manage_asset_tags');

        Route::delete('/tags/{tag}', [AssetTagsController::class, 'destroy'])
            ->name('tags.destroy')
            ->middleware('permission:manage_asset_tags');

        // Constrain {asset} so it won’t eat "rentals" or "tags"
        Route::get('/{asset}', [AssetsController::class, 'show'])
            ->name('show')
            ->middleware('permission:manage_assets')
            ->whereNumber('asset');

        Route::get('/{asset}/edit', [AssetsController::class, 'edit'])
            ->name('edit')
            ->middleware('permission:manage_assets')
            ->whereNumber('asset');

        Route::put('/{asset}', [AssetsController::class, 'update'])
            ->name('update')
            ->middleware('permission:manage_assets')
            ->whereNumber('asset');

        Route::delete('/{asset}', [AssetsController::class, 'destroy'])
            ->name('destroy')
            ->middleware('permission:manage_assets')
            ->whereNumber('asset');
    });

    // --------------------
    // Profile
    // --------------------
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile/name', [ProfileController::class, 'updateName'])->name('profile.updateName');
    Route::post('/profile/email/request', [ProfileController::class, 'requestEmailChange'])->name('profile.requestEmailChange');
    Route::post('/profile/email/confirm', [ProfileController::class, 'confirmEmailChange'])->name('profile.confirmEmailChange');
    Route::post('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.updatePassword');

    // 2FA
    Route::post('/profile/2fa/enable', [TwoFactorController::class, 'enable'])->name('profile.2fa.enable');
    Route::post('/profile/2fa/confirm', [TwoFactorController::class, 'confirm'])->name('profile.2fa.confirm');
    Route::post('/profile/2fa/disable', [TwoFactorController::class, 'disable'])->name('profile.2fa.disable');

    // --------------------
    // Settings
    // --------------------
    Route::prefix('settings')->name('settings.')->group(function () {

        Route::get('/portal', [PortalSettingsController::class, 'edit'])
            ->name('portal.edit')
            ->middleware('permission:manage_portal_settings');

        Route::post('/portal', [PortalSettingsController::class, 'update'])
            ->name('portal.update')
            ->middleware('permission:manage_portal_settings');

        // Users
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

        // SMTP
        Route::get('/smtp', [SmtpSettingsController::class, 'edit'])
            ->name('smtp.edit')
            ->middleware('permission:manage_smtp_settings');

        Route::put('/smtp', [SmtpSettingsController::class, 'update'])
            ->name('smtp.update')
            ->middleware('permission:manage_smtp_settings');

        Route::post('/smtp/test', [SmtpSettingsController::class, 'test'])
            ->name('smtp.test')
            ->middleware('permission:manage_smtp_settings');

        // Permission sets
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

        // --------------------
        // Asset Types
        // --------------------
        Route::get('/asset-types', [AssetTypesController::class, 'index'])
            ->name('assetTypes.index')
            ->middleware('permission:manage_asset_types');

        Route::post('/asset-types', [AssetTypesController::class, 'store'])
            ->name('assetTypes.store')
            ->middleware('permission:manage_asset_types');

        Route::put('/asset-types/{assetType}', [AssetTypesController::class, 'update'])
            ->name('assetTypes.update')
            ->middleware('permission:manage_asset_types');

        Route::delete('/asset-types/{assetType}', [AssetTypesController::class, 'destroy'])
            ->name('assetTypes.destroy')
            ->middleware('permission:manage_asset_types');

        // --------------------
        // Owner Entities
        // --------------------
        Route::get('/owner-entities', [OwnerEntitiesController::class, 'index'])
            ->name('ownerEntities.index')
            ->middleware('permission:manage_owner_entities');

        Route::post('/owner-entities', [OwnerEntitiesController::class, 'store'])
            ->name('ownerEntities.store')
            ->middleware('permission:manage_owner_entities');

        Route::put('/owner-entities/{ownerEntity}', [OwnerEntitiesController::class, 'update'])
            ->name('ownerEntities.update')
            ->middleware('permission:manage_owner_entities');

        Route::delete('/owner-entities/{ownerEntity}', [OwnerEntitiesController::class, 'destroy'])
            ->name('ownerEntities.destroy')
            ->middleware('permission:manage_owner_entities');
    });
});

// 2FA challenge routes (auth but NOT 2fa middleware)
Route::middleware('auth')->group(function () {
    Route::get('/two-factor', [TwoFactorController::class, 'challengeForm'])->name('2fa.challenge');
    Route::post('/two-factor', [TwoFactorController::class, 'challengeVerify'])->name('2fa.verify');
});

require __DIR__ . '/auth.php';
