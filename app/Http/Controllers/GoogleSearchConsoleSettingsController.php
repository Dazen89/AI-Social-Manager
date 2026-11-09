<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveGoogleSearchConsoleSettingsRequest;
use App\Models\GoogleSearchConsoleConnection;
use App\Models\IntegrationSetting;
use App\Models\Site;
use App\Services\GoogleSearchConsoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GoogleSearchConsoleSettingsController extends Controller
{
    public function edit(Site $site, GoogleSearchConsoleService $searchConsole): View
    {
        $setting = IntegrationSetting::query()
            ->where('provider', IntegrationSetting::GOOGLE_SEARCH_CONSOLE)
            ->first();

        return view('sites.integrations.google-search-console.settings', [
            'setting' => $setting,
            'clientId' => (string) data_get($setting?->configuration, 'client_id', ''),
            'environmentManaged' => $searchConsole->environmentCredentialsAreConfigured(),
            'redirectUri' => $searchConsole->redirectUri(),
            'site' => $site,
            'hasConnections' => GoogleSearchConsoleConnection::query()->exists(),
        ]);
    }

    public function update(SaveGoogleSearchConsoleSettingsRequest $request, Site $site, GoogleSearchConsoleService $searchConsole): RedirectResponse
    {
        if ($searchConsole->environmentCredentialsAreConfigured()) {
            return back()->with('integration_error', 'Le credenziali sono gestite dalle variabili d’ambiente e non possono essere modificate dalla dashboard.');
        }

        $setting = IntegrationSetting::query()
            ->where('provider', IntegrationSetting::GOOGLE_SEARCH_CONSOLE)
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

        if ($clientIdChanged && GoogleSearchConsoleConnection::query()->exists()) {
            return back()
                ->withInput($request->safe()->except('client_secret'))
                ->withErrors([
                    'client_id' => 'Prima di cambiare Client ID scollega Search Console da tutti i siti: i token esistenti appartengono al client attuale.',
                ]);
        }

        IntegrationSetting::query()->updateOrCreate([
            'provider' => IntegrationSetting::GOOGLE_SEARCH_CONSOLE,
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
            ->with('status', 'Credenziali Search Console salvate. Ora puoi collegare l’account Google.');
    }

    public function destroy(Site $site, GoogleSearchConsoleService $searchConsole): RedirectResponse
    {
        if ($searchConsole->environmentCredentialsAreConfigured()) {
            return back()->with('integration_error', 'Le credenziali gestite dall’ambiente non possono essere cancellate dalla dashboard.');
        }

        if (GoogleSearchConsoleConnection::query()->exists()) {
            return back()->with('integration_error', 'Prima di cancellare le credenziali scollega Search Console da tutti i siti.');
        }

        IntegrationSetting::query()
            ->where('provider', IntegrationSetting::GOOGLE_SEARCH_CONSOLE)
            ->delete();

        return redirect()
            ->route('google-search-console.settings.edit', $site)
            ->with('status', 'Configurazione Search Console rimossa.');
    }
}
