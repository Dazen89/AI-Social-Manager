<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveGoogleSearchConsoleSettingsRequest;
use App\Models\GoogleSearchConsoleConnection;
use App\Models\IntegrationSetting;
use App\Models\Site;
use App\Services\GoogleSearchConsoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GoogleSearchConsoleSettingsController extends Controller
{
    public function edit(Request $request, GoogleSearchConsoleService $searchConsole): View
    {
        $setting = IntegrationSetting::query()
            ->where('provider', IntegrationSetting::GOOGLE_SEARCH_CONSOLE)
            ->first();
        $returnSite = $request->integer('site')
            ? Site::query()->find($request->integer('site'))
            : null;

        return view('settings.integrations.google-search-console', [
            'setting' => $setting,
            'clientId' => (string) data_get($setting?->configuration, 'client_id', ''),
            'environmentManaged' => $searchConsole->environmentCredentialsAreConfigured(),
            'redirectUri' => $searchConsole->redirectUri(),
            'returnSite' => $returnSite,
            'hasConnections' => GoogleSearchConsoleConnection::query()->exists(),
        ]);
    }

    public function update(SaveGoogleSearchConsoleSettingsRequest $request, GoogleSearchConsoleService $searchConsole): RedirectResponse
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

        $returnSite = $request->validated('return_site_id')
            ? Site::query()->find($request->validated('return_site_id'))
            : null;

        if ($returnSite) {
            return redirect()
                ->route('sites.edit', $returnSite)
                ->with('status', 'Credenziali Search Console salvate. Ora puoi collegare l’account Google.');
        }

        return redirect()
            ->route('settings.google-search-console.edit')
            ->with('status', 'Credenziali Search Console salvate in forma cifrata. Completa un collegamento per verificarle.');
    }

    public function destroy(GoogleSearchConsoleService $searchConsole): RedirectResponse
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
            ->route('settings.google-search-console.edit')
            ->with('status', 'Configurazione Search Console rimossa.');
    }
}
