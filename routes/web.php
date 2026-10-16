<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GoogleAnalyticsConnectionController;
use App\Http\Controllers\GoogleAnalyticsSettingsController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::middleware('admin')->group(function (): void {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::resource('sites', SiteController::class)->only(['index', 'create', 'store', 'edit', 'update']);

        Route::get('/settings/integrations/google-analytics', [GoogleAnalyticsSettingsController::class, 'edit'])
            ->name('settings.google-analytics.edit');
        Route::put('/settings/integrations/google-analytics', [GoogleAnalyticsSettingsController::class, 'update'])
            ->name('settings.google-analytics.update');
        Route::delete('/settings/integrations/google-analytics', [GoogleAnalyticsSettingsController::class, 'destroy'])
            ->name('settings.google-analytics.destroy');

        Route::get('/sites/{site}/integrations/google-analytics/connect', [GoogleAnalyticsConnectionController::class, 'connect'])
            ->name('google-analytics.connect');
        Route::get('/integrations/google-analytics/callback', [GoogleAnalyticsConnectionController::class, 'callback'])
            ->name('google-analytics.callback');
        Route::get('/sites/{site}/integrations/google-analytics/properties', [GoogleAnalyticsConnectionController::class, 'properties'])
            ->name('google-analytics.properties');
        Route::post('/sites/{site}/integrations/google-analytics', [GoogleAnalyticsConnectionController::class, 'store'])
            ->name('google-analytics.store');
        Route::delete('/sites/{site}/integrations/google-analytics/pending', [GoogleAnalyticsConnectionController::class, 'cancel'])
            ->name('google-analytics.cancel');
        Route::delete('/sites/{site}/integrations/google-analytics', [GoogleAnalyticsConnectionController::class, 'destroy'])
            ->name('google-analytics.destroy');
    });
});
