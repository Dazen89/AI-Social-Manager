<?php

namespace App\Http\Controllers;

use App\Exceptions\AiServiceException;
use App\Exceptions\GoogleAnalyticsException;
use App\Models\AiIntegration;
use App\Models\Site;
use App\Services\AnalyticsInsightService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AnalyticsInsightController extends Controller
{
    public function store(Request $request, Site $site, AnalyticsInsightService $insights): RedirectResponse
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

        try {
            $result = $insights->generate($site, $property, $integration, $days);
        } catch (AiServiceException|GoogleAnalyticsException $exception) {
            return $this->failure($site, $days, $exception->getMessage());
        }

        $message = $result['reused']
            ? 'I dati non sono cambiati: mostro l’analisi già disponibile senza effettuare una nuova chiamata AI.'
            : 'Analisi AI completata e salvata.';

        return redirect()
            ->route('sites.show', ['site' => $site, 'range' => $days])
            ->with('analytics_insight_status', $message);
    }

    private function failure(Site $site, int $days, string $message): RedirectResponse
    {
        return redirect()
            ->route('sites.show', ['site' => $site, 'range' => $days])
            ->with('analytics_insight_error', $message);
    }
}
