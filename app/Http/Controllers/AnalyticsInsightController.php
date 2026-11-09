<?php

namespace App\Http\Controllers;

use App\Jobs\RunAnalyticsInsight;
use App\Models\AiIntegration;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AnalyticsInsightController extends Controller
{
    public function store(Request $request, Site $site): RedirectResponse
    {
        $validated = $request->validate([
            'range' => ['required', 'integer', 'in:7,28,90'],
        ]);
        $days = (int) $validated['range'];
        $site->load('googleAnalyticsProperty.connection');
        $property = $site->googleAnalyticsProperty;
        $integration = AiIntegration::query()
            ->where('active_marker', 'active')
            ->first();

        if (! $property || $property->connection?->status !== 'active') {
            return $this->failure($site, $days, 'Collega Google Analytics prima di richiedere l’analisi AI.');
        }

        if (! $integration) {
            return $this->failure($site, $days, 'Configura e attiva un’integrazione AI prima di generare l’analisi.');
        }

        if ($integration->status !== 'verified') {
            return $this->failure($site, $days, 'L’integrazione AI attiva deve essere verificata prima di generare l’analisi.');
        }

        $key = RunAnalyticsInsight::statusKey($site->id, $days);
        if (Cache::add($key, ['state' => 'pending', 'message' => 'Analisi AI in attesa o in corso. Ricarica la pagina tra poco per vedere il risultato.'], now()->addMinutes(10))) {
            RunAnalyticsInsight::dispatch($site->id, $integration->id, $days);
        } elseif (data_get(Cache::get($key), 'state') !== 'pending') {
            Cache::forget($key);
            Cache::put($key, ['state' => 'pending', 'message' => 'Analisi AI in attesa o in corso. Ricarica la pagina tra poco per vedere il risultato.'], now()->addMinutes(10));
            RunAnalyticsInsight::dispatch($site->id, $integration->id, $days);
        }

        return redirect()
            ->route('sites.show', ['site' => $site, 'range' => $days])
            ->with('analytics_insight_status', 'Analisi AI accodata. Ricarica la pagina tra poco per vedere il risultato.');
    }

    private function failure(Site $site, int $days, string $message): RedirectResponse
    {
        return redirect()
            ->route('sites.show', ['site' => $site, 'range' => $days])
            ->with('analytics_insight_error', $message);
    }
}
