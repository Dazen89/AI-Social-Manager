<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveAiIntegrationRequest;
use App\Models\IntegrationSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AiIntegrationSettingsController extends Controller
{
    public function edit(): View
    {
        $setting = $this->setting();

        return view('settings.integrations.ai', [
            'setting' => $setting,
            'providers' => config('ai.providers', []),
            'selectedProvider' => (string) data_get($setting?->configuration, 'provider', ''),
            'selectedModel' => (string) data_get($setting?->configuration, 'model', ''),
        ]);
    }

    public function update(SaveAiIntegrationRequest $request): RedirectResponse
    {
        $setting = $this->setting();
        $configuration = $setting?->configuration ?? [];
        $provider = $request->validated('ai_provider');
        $apiKey = $request->validated('api_key');

        if (blank($apiKey) && data_get($configuration, 'provider') !== $provider) {
            return back()
                ->withInput($request->safe()->except('api_key'))
                ->withErrors(['api_key' => 'Inserisci una API key valida per il fornitore selezionato.']);
        }

        $apiKey ??= data_get($configuration, 'api_key');

        if (blank($apiKey)) {
            return back()
                ->withInput($request->safe()->except('api_key'))
                ->withErrors(['api_key' => 'Inserisci la API key generata dal fornitore.']);
        }

        IntegrationSetting::query()->updateOrCreate([
            'provider' => IntegrationSetting::AI,
        ], [
            'configuration' => [
                'provider' => $provider,
                'model' => $request->validated('model'),
                'api_key' => $apiKey,
            ],
            'configured_by_user_id' => $request->user()->id,
            'configured_at' => now(),
            'status' => 'configured',
            'last_tested_at' => null,
            'last_error' => null,
        ]);

        return redirect()
            ->route('settings.ai.edit')
            ->with('status', 'Integrazione AI salvata. La API key è memorizzata in forma cifrata.');
    }

    public function destroy(): RedirectResponse
    {
        IntegrationSetting::query()
            ->where('provider', IntegrationSetting::AI)
            ->delete();

        return redirect()
            ->route('settings.ai.edit')
            ->with('status', 'Configurazione AI rimossa.');
    }

    private function setting(): ?IntegrationSetting
    {
        return IntegrationSetting::query()
            ->where('provider', IntegrationSetting::AI)
            ->first();
    }
}
