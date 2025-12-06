<?php

use App\Http\Controllers\Settings\IntegrationSettingsController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('user-password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/appearance');
    })->name('appearance.edit');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');

    // Integration settings
    Route::prefix('settings/integrations')->name('settings.integrations.')->group(function () {
        Route::get('/', [IntegrationSettingsController::class, 'index'])->name('index');
        Route::get('/show', [IntegrationSettingsController::class, 'show'])->name('show');
        Route::patch('/', [IntegrationSettingsController::class, 'update'])->name('update');
        Route::get('/health', [IntegrationSettingsController::class, 'checkUserHealth'])->name('health');
        Route::get('/health/{provider}', [IntegrationSettingsController::class, 'checkHealth'])->name('health.provider');
        Route::get('/models', [IntegrationSettingsController::class, 'listUserModels'])->name('models');
        Route::get('/models/{provider}', [IntegrationSettingsController::class, 'listModels'])->name('models.provider');
    });
});
