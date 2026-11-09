<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveGoogleAnalyticsSettingsRequest;
use App\Models\GoogleAnalyticsConnection;
use App\Models\IntegrationSetting;
use App\Models\Site;
use App\Services\GoogleAnalyticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GoogleAnalyticsSettingsController extends Controller
{
    public function edit(Site $site, GoogleAnalyticsService $analytics): View
    {
        $setting = IntegrationSetting::query()
            ->where('provider', IntegrationSetting::GOOGLE_ANALYTICS)
            ->first();

        return view('sites.integrations.google-analytics.settings', [
            'setting' => $setting,
            'clientId' => (string) data_get($setting?->configuration, 'client_id', ''),
            'environmentManaged' => $analytics->environmentCredentialsAreConfigured(),
            'redirectUri' => $analytics->redirectUri(),
            'site' => $site,
            'hasConnections' => GoogleAnalyticsConnection::query()->exists(),
        ]);
    }

    public function update(SaveGoogleAnalyticsSettingsRequest $request, Site $site, GoogleAnalyticsService $analytics): RedirectResponse
    {
        if ($analytics->environmentCredentialsAreConfigured()) {
            return back()->with('integration_error', 'Le credenziali sono gestite dalle variabili d’ambiente e non possono essere modificate dalla dashboard.');
        }

        $setting = IntegrationSetting::query()
            ->where('provider', IntegrationSetting::GOOGLE_ANALYTICS)
            ->first();
        $existingConfiguration = $setting?->configuration ?? [];
        $clientId = $request->validated('client_id');
        $clientSecret = $request->validated('client_secret')
            ?: data_get($existingConfiguration, 'client_secret');

        if (blank($clientSecret)) {
            return back()
                ->withInput($request->safe()->except('client_secret'))
                ->withErrors(['client_secret' => 'Inserisci il Client Secret generato da Google Cloud.']);
        }

        $clientIdChanged = filled(data_get($existingConfiguration, 'client_id'))
            && data_get($existingConfiguration, 'client_id') !== $clientId;

        if ($clientIdChanged && GoogleAnalyticsConnection::query()->exists()) {
            return back()
                ->withInput($request->safe()->except('client_secret'))
                ->withErrors([
                    'client_id' => 'Prima di cambiare Client ID scollega Google Analytics da tutti i siti: i token esistenti appartengono al client attuale.',
                ]);
        }

        IntegrationSetting::query()->updateOrCreate([
            'provider' => IntegrationSetting::GOOGLE_ANALYTICS,
        ], [
            'configuration' => [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ],
            'configured_by_user_id' => $request->user()->id,
            'configured_at' => now(),
            'status' => 'configured',
            'last_tested_at' => null,
            'last_error' => null,
        ]);

        return redirect()
            ->route('sites.edit', $site)
            ->with('status', 'Credenziali Google Analytics salvate. Ora puoi collegare l’account Google.');
    }

    public function destroy(Site $site, GoogleAnalyticsService $analytics): RedirectResponse
    {
        if ($analytics->environmentCredentialsAreConfigured()) {
            return back()->with('integration_error', 'Le credenziali gestite dall’ambiente non possono essere cancellate dalla dashboard.');
        }

        if (GoogleAnalyticsConnection::query()->exists()) {
            return back()->with('integration_error', 'Prima di cancellare le credenziali scollega Google Analytics da tutti i siti.');
        }

        IntegrationSetting::query()
            ->where('provider', IntegrationSetting::GOOGLE_ANALYTICS)
            ->delete();

        return redirect()
            ->route('google-analytics.settings.edit', $site)
            ->with('status', 'Configurazione Google Analytics rimossa.');
    }
}
