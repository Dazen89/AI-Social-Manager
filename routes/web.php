<?php

use App\Http\Controllers\AiIntegrationSettingsController;
use App\Http\Controllers\AnalyticsInsightController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GoogleAnalyticsConnectionController;
use App\Http\Controllers\GoogleAnalyticsSettingsController;
use App\Http\Controllers\GoogleSearchConsoleConnectionController;
use App\Http\Controllers\GoogleSearchConsoleSettingsController;
use App\Http\Controllers\SiteAnalyticsOverviewController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SiteEditorialPlanController;
use App\Http\Controllers\XConnectionController;
use App\Http\Controllers\XSettingsController;
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
        Route::get('/sites/{site}/editorial-plan', [SiteEditorialPlanController::class, 'show'])->name('sites.editorial-plan.show');
        Route::post('/sites/{site}/editorial-plan/sources', [SiteEditorialPlanController::class, 'store'])->name('sites.editorial-plan.sources.store');
        Route::put('/sites/{site}/editorial-plan/sources/{source}', [SiteEditorialPlanController::class, 'update'])->name('sites.editorial-plan.sources.update');
        Route::patch('/sites/{site}/editorial-plan/sources/{source}/toggle', [SiteEditorialPlanController::class, 'toggle'])->name('sites.editorial-plan.sources.toggle');
        Route::post('/sites/{site}/analytics/insights', [AnalyticsInsightController::class, 'store'])
            ->name('sites.analytics-insights.store');

        Route::get('/settings/integrations/google-analytics', [GoogleAnalyticsSettingsController::class, 'edit'])
            ->name('settings.google-analytics.edit');
        Route::put('/settings/integrations/google-analytics', [GoogleAnalyticsSettingsController::class, 'update'])
            ->name('settings.google-analytics.update');
        Route::delete('/settings/integrations/google-analytics', [GoogleAnalyticsSettingsController::class, 'destroy'])
            ->name('settings.google-analytics.destroy');

        Route::get('/settings/integrations/google-search-console', [GoogleSearchConsoleSettingsController::class, 'edit'])
            ->name('settings.google-search-console.edit');
        Route::put('/settings/integrations/google-search-console', [GoogleSearchConsoleSettingsController::class, 'update'])
            ->name('settings.google-search-console.update');
        Route::delete('/settings/integrations/google-search-console', [GoogleSearchConsoleSettingsController::class, 'destroy'])
            ->name('settings.google-search-console.destroy');

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

        Route::get('/settings/integrations/x', [XSettingsController::class, 'edit'])
            ->name('settings.x.edit');
        Route::put('/settings/integrations/x', [XSettingsController::class, 'update'])
            ->name('settings.x.update');
        Route::delete('/settings/integrations/x', [XSettingsController::class, 'destroy'])
            ->name('settings.x.destroy');
        Route::get('/integrations/x/connect', [XConnectionController::class, 'connect'])
            ->name('x.connect');
        Route::get('/integrations/x/callback', [XConnectionController::class, 'callback'])
            ->name('x.callback');
        Route::delete('/integrations/x', [XConnectionController::class, 'destroy'])
            ->name('x.destroy');

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

        Route::get('/sites/{site}/integrations/google-search-console/connect', [GoogleSearchConsoleConnectionController::class, 'connect'])
            ->name('google-search-console.connect');
        Route::get('/integrations/google-search-console/callback', [GoogleSearchConsoleConnectionController::class, 'callback'])
            ->name('google-search-console.callback');
        Route::get('/sites/{site}/integrations/google-search-console/properties', [GoogleSearchConsoleConnectionController::class, 'properties'])
            ->name('google-search-console.properties');
        Route::post('/sites/{site}/integrations/google-search-console', [GoogleSearchConsoleConnectionController::class, 'store'])
            ->name('google-search-console.store');
        Route::delete('/sites/{site}/integrations/google-search-console/pending', [GoogleSearchConsoleConnectionController::class, 'cancel'])
            ->name('google-search-console.cancel');
        Route::delete('/sites/{site}/integrations/google-search-console', [GoogleSearchConsoleConnectionController::class, 'destroy'])
            ->name('google-search-console.destroy');
    });
});
