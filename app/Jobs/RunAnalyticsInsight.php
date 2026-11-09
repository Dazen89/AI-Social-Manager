<?php

namespace App\Jobs;

use App\Exceptions\AiServiceException;
use App\Exceptions\GoogleAnalyticsException;
use App\Models\AiIntegration;
use App\Models\Site;
use App\Services\AnalyticsInsightService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RunAnalyticsInsight implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 1;

    public int $timeout = 240;

    public int $uniqueFor = 600;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $siteId, public readonly int $integrationId, public readonly int $days) {}

    public function uniqueId(): string
    {
        return $this->siteId.':'.$this->days;
    }

    public static function statusKey(int $siteId, int $days): string
    {
        return 'analytics-insight-job:'.$siteId.':'.$days;
    }

    public function handle(AnalyticsInsightService $insights): void
    {
        try {
            $site = Site::query()->with('googleAnalyticsProperty.connection')->findOrFail($this->siteId);
            $integration = AiIntegration::query()->findOrFail($this->integrationId);
            $property = $site->googleAnalyticsProperty;

            if (! $property || $property->connection?->status !== 'active') {
                throw new AiServiceException('Ricollega Google Analytics prima di richiedere l’analisi AI.');
            }

            $result = $insights->generate($site, $property, $integration, $this->days);
            Cache::put(self::statusKey($this->siteId, $this->days), [
                'state' => 'completed',
                'message' => $result['reused']
                    ? 'I dati non sono cambiati: mostro l’analisi già disponibile senza effettuare una nuova chiamata AI.'
                    : 'Analisi AI completata e salvata.',
            ], now()->addHour());
        } catch (AiServiceException|GoogleAnalyticsException $exception) {
            $this->recordFailure($exception->getMessage());
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->recordFailure('Analisi AI non completata. Riprova; se il problema persiste, verifica il worker e il servizio AI.');
    }

    private function recordFailure(string $message): void
    {
        Cache::put(self::statusKey($this->siteId, $this->days), ['state' => 'failed', 'message' => $message], now()->addHour());
    }
}
