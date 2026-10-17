<?php

use App\Http\Controllers\AiIntegrationSettingsController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GoogleAnalyticsConnectionController;
use App\Http\Controllers\GoogleAnalyticsSettingsController;
use App\Http\Controllers\SiteAnalyticsOverviewController;
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
        Route::get('/sites/{site}', SiteAnalyticsOverviewController::class)->name('sites.show');

        Route::get('/settings/integrations/google-analytics', [GoogleAnalyticsSettingsController::class, 'edit'])
            ->name('settings.google-analytics.edit');
        Route::put('/settings/integrations/google-analytics', [GoogleAnalyticsSettingsController::class, 'update'])
            ->name('settings.google-analytics.update');
        Route::delete('/settings/integrations/google-analytics', [GoogleAnalyticsSettingsController::class, 'destroy'])
            ->name('settings.google-analytics.destroy');

        Route::get('/settings/integrations/ai', [AiIntegrationSettingsController::class, 'index'])
            ->name('settings.ai.index');
        Route::get('/settings/integrations/ai/create', [AiIntegrationSettingsController::class, 'create'])
            ->name('settings.ai.create');
        Route::post('/settings/integrations/ai', [AiIntegrationSettingsController::class, 'store'])
            ->name('settings.ai.store');
        Route::get('/settings/integrations/ai/{aiIntegration}/edit', [AiIntegrationSettingsController::class, 'edit'])
            ->name('settings.ai.edit');
        Route::put('/settings/integrations/ai/{aiIntegration}', [AiIntegrationSettingsController::class, 'update'])
            ->name('settings.ai.update');
        Route::post('/settings/integrations/ai/{aiIntegration}/activate', [AiIntegrationSettingsController::class, 'activate'])
            ->name('settings.ai.activate');
        Route::post('/settings/integrations/ai/{aiIntegration}/verify', [AiIntegrationSettingsController::class, 'verify'])
            ->name('settings.ai.verify');
        Route::delete('/settings/integrations/ai/{aiIntegration}', [AiIntegrationSettingsController::class, 'destroy'])
            ->name('settings.ai.destroy');

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
