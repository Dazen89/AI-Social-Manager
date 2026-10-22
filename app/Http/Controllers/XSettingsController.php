<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveXSettingsRequest;
use App\Models\IntegrationSetting;
use App\Models\XConnection;
use App\Services\XService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class XSettingsController extends Controller
{
    public function edit(XService $x): View
    {
        $setting = IntegrationSetting::query()
            ->where('provider', IntegrationSetting::X)
            ->first();

        return view('settings.integrations.x', [
            'setting' => $setting,
            'clientId' => (string) data_get($setting?->configuration, 'client_id', ''),
            'environmentManaged' => $x->environmentCredentialsAreConfigured(),
            'redirectUri' => $x->redirectUri(),
            'scopes' => $x->scopes(),
            'connection' => XConnection::query()->first(),
        ]);
    }

    public function update(SaveXSettingsRequest $request, XService $x): RedirectResponse
    {
        if ($x->environmentCredentialsAreConfigured()) {
            return back()->with('integration_error', 'Le credenziali X sono gestite dalle variabili d’ambiente e non possono essere modificate dalla dashboard.');
        }

        $setting = IntegrationSetting::query()
            ->where('provider', IntegrationSetting::X)
            ->first();
        $existingConfiguration = $setting?->configuration ?? [];
        $clientId = $request->validated('client_id');
        $clientSecret = $request->validated('client_secret')
            ?: data_get($existingConfiguration, 'client_secret');

        if (blank($clientSecret)) {
            return back()
                ->withInput($request->safe()->except('client_secret'))
                ->withErrors(['client_secret' => 'Inserisci il Client Secret dell’app X.']);
        }

        $clientIdChanged = filled(data_get($existingConfiguration, 'client_id'))
            && data_get($existingConfiguration, 'client_id') !== $clientId;

        if (XConnection::query()->exists() && (blank(data_get($existingConfiguration, 'client_id')) || $clientIdChanged)) {
            return back()
                ->withInput($request->safe()->except('client_secret'))
                ->withErrors([
                    'client_id' => 'Prima di cambiare Client ID scollega l’account X: i token esistenti appartengono all’app attuale.',
                ]);
        }

        IntegrationSetting::query()->updateOrCreate([
            'provider' => IntegrationSetting::X,
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
            ->route('settings.x.edit')
            ->with('status', 'Credenziali X salvate in forma cifrata. Ora collega l’account che pubblicherà i post.');
    }

    public function destroy(XService $x): RedirectResponse
    {
        if ($x->environmentCredentialsAreConfigured()) {
            return back()->with('integration_error', 'Le credenziali X gestite dall’ambiente non possono essere cancellate dalla dashboard.');
        }

        if (XConnection::query()->exists()) {
            return back()->with('integration_error', 'Prima di cancellare le credenziali scollega l’account X.');
        }

        IntegrationSetting::query()
            ->where('provider', IntegrationSetting::X)
            ->delete();

        return redirect()
            ->route('settings.x.edit')
            ->with('status', 'Configurazione X rimossa.');
    }
}
