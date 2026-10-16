<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveGoogleAnalyticsSettingsRequest;
use App\Models\GoogleAnalyticsConnection;
use App\Models\IntegrationSetting;
use App\Models\Site;
use App\Services\GoogleAnalyticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GoogleAnalyticsSettingsController extends Controller
{
    public function edit(Request $request, GoogleAnalyticsService $analytics): View
    {
        $setting = IntegrationSetting::query()
            ->where('provider', IntegrationSetting::GOOGLE_ANALYTICS)
            ->first();
        $returnSite = $request->integer('site')
            ? Site::query()->find($request->integer('site'))
            : null;

        return view('settings.integrations.google-analytics', [
            'setting' => $setting,
            'clientId' => (string) data_get($setting?->configuration, 'client_id', ''),
            'environmentManaged' => $analytics->environmentCredentialsAreConfigured(),
            'redirectUri' => $analytics->redirectUri(),
            'returnSite' => $returnSite,
            'hasConnections' => GoogleAnalyticsConnection::query()->exists(),
        ]);
    }

    public function update(SaveGoogleAnalyticsSettingsRequest $request, GoogleAnalyticsService $analytics): RedirectResponse
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

        $returnSite = $request->validated('return_site_id')
            ? Site::query()->find($request->validated('return_site_id'))
            : null;

        if ($returnSite) {
            return redirect()
                ->route('sites.edit', $returnSite)
                ->with('status', 'Credenziali Google Analytics salvate. Ora puoi collegare l’account Google.');
        }

        return redirect()
            ->route('settings.google-analytics.edit')
            ->with('status', 'Credenziali Google Analytics salvate in forma cifrata. Completa un collegamento per verificarle.');
    }

    public function destroy(GoogleAnalyticsService $analytics): RedirectResponse
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
            ->route('settings.google-analytics.edit')
            ->with('status', 'Configurazione Google Analytics rimossa.');
    }
}
