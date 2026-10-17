<?php

namespace App\Http\Controllers;

use App\Exceptions\AiServiceException;
use App\Http\Requests\SaveAiIntegrationRequest;
use App\Models\AiIntegration;
use App\Services\AiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AiIntegrationSettingsController extends Controller
{
    public function index(): View
    {
        return view('settings.integrations.ai-index', [
            'integrations' => AiIntegration::query()
                ->orderByDesc('active_marker')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return $this->form();
    }

    public function store(SaveAiIntegrationRequest $request): RedirectResponse
    {
        $provider = (string) $request->validated('ai_provider');
        $apiKey = (string) $request->validated('api_key');

        if (blank($apiKey)) {
            return back()
                ->withInput($request->safe()->except('api_key'))
                ->withErrors(['api_key' => 'Inserisci la API key generata dal fornitore.']);
        }

        DB::transaction(function () use ($request, $provider, $apiKey): void {
            $hasActiveIntegration = AiIntegration::query()
                ->where('active_marker', 'active')
                ->exists();

            AiIntegration::query()->create([
                'name' => $this->integrationName($request, $provider),
                'provider' => $provider,
                'model' => $request->validated('model'),
                'api_key' => $apiKey,
                'active_marker' => $hasActiveIntegration ? null : 'active',
                'configured_by_user_id' => $request->user()->id,
                'configured_at' => now(),
                'status' => 'configured',
                'last_tested_at' => null,
                'last_error' => null,
            ]);
        });

        return redirect()
            ->route('settings.ai.index')
            ->with('status', 'Integrazione AI creata. Le credenziali sono cifrate; verifica ora il collegamento.');
    }

    public function edit(AiIntegration $aiIntegration): View
    {
        return $this->form($aiIntegration);
    }

    public function update(SaveAiIntegrationRequest $request, AiIntegration $aiIntegration): RedirectResponse
    {
        $provider = (string) $request->validated('ai_provider');
        $apiKey = $request->validated('api_key');

        if (blank($apiKey) && $aiIntegration->provider !== $provider) {
            return back()
                ->withInput($request->safe()->except('api_key'))
                ->withErrors(['api_key' => 'Inserisci una API key valida per il nuovo fornitore selezionato.']);
        }

        $aiIntegration->update([
            'name' => $this->integrationName($request, $provider),
            'provider' => $provider,
            'model' => $request->validated('model'),
            'api_key' => $apiKey ?: $aiIntegration->api_key,
            'configured_by_user_id' => $request->user()->id,
            'configured_at' => now(),
            'status' => 'configured',
            'last_tested_at' => null,
            'last_error' => null,
        ]);

        return redirect()
            ->route('settings.ai.index')
            ->with('status', 'Integrazione AI aggiornata. Verifica nuovamente le credenziali prima dell’uso.');
    }

    public function activate(AiIntegration $aiIntegration): RedirectResponse
    {
        DB::transaction(function () use ($aiIntegration): void {
            AiIntegration::query()
                ->where('active_marker', 'active')
                ->where('id', '!=', $aiIntegration->id)
                ->update(['active_marker' => null]);

            $aiIntegration->update(['active_marker' => 'active']);
        });

        return redirect()
            ->route('settings.ai.index')
            ->with('status', "{$aiIntegration->name} è ora l’integrazione AI attiva.");
    }

    public function verify(AiIntegration $aiIntegration, AiService $ai): RedirectResponse
    {
        try {
            $ai->testConnection($aiIntegration);
        } catch (AiServiceException $exception) {
            $aiIntegration->update([
                'status' => 'error',
                'last_error' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('settings.ai.index')
                ->with('integration_error', 'Verifica non riuscita: '.$exception->getMessage());
        }

        $aiIntegration->update([
            'status' => 'verified',
            'last_tested_at' => now(),
            'last_error' => null,
        ]);

        return redirect()
            ->route('settings.ai.index')
            ->with('status', "{$aiIntegration->name}: credenziali e modello verificati correttamente.");
    }

    public function destroy(AiIntegration $aiIntegration): RedirectResponse
    {
        $deletedName = $aiIntegration->name;
        $activatedName = DB::transaction(function () use ($aiIntegration): ?string {
            $wasActive = $aiIntegration->is_active;
            $aiIntegration->delete();

            if (! $wasActive) {
                return null;
            }

            $replacement = AiIntegration::query()
                ->orderByDesc('updated_at')
                ->first();

            $replacement?->update(['active_marker' => 'active']);

            return $replacement?->name;
        });

        $message = "Integrazione {$deletedName} rimossa.";

        if ($activatedName) {
            $message .= " {$activatedName} è stata attivata automaticamente.";
        }

        return redirect()
            ->route('settings.ai.index')
            ->with('status', $message);
    }

    private function form(?AiIntegration $integration = null): View
    {
        return view('settings.integrations.ai', [
            'integration' => $integration,
            'providers' => config('ai.providers', []),
            'selectedProvider' => (string) old('ai_provider', $integration?->provider),
            'selectedModel' => (string) old('model', $integration?->model),
        ]);
    }

    private function integrationName(SaveAiIntegrationRequest $request, string $provider): string
    {
        return (string) ($request->validated('name')
            ?: config("ai.providers.{$provider}.name", ucfirst($provider)));
    }
}
