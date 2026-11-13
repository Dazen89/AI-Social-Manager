<?php

namespace App\Console\Commands;

use App\Exceptions\GoogleAnalyticsException;
use App\Models\Site;
use App\Services\GoogleAnalyticsReportService;
use Illuminate\Console\Command;

class DiagnoseAnalytics extends Command
{
    protected $signature = 'analytics:diagnose {site? : ID del sito da verificare}';

    protected $description = 'Prova un singolo report Analytics minimale, rispettando cache e pause';

    public function handle(GoogleAnalyticsReportService $reports): int
    {
        $siteId = $this->argument('site');

        if ($siteId !== null && ! ctype_digit((string) $siteId)) {
            $this->error('Specifica un ID numerico del sito.');
            return self::FAILURE;
        }

        $sites = Site::query()->where('is_active', true)
            ->whereHas('googleAnalyticsProperty.connection', fn ($query) => $query->where('status', 'active'))
            ->when($siteId !== null, fn ($query) => $query->whereKey($siteId))
            ->with('googleAnalyticsProperty.connection')->limit(2)->get();

        if ($sites->count() !== 1) {
            $this->error($sites->isEmpty() ? 'Nessun sito attivo con Analytics collegato.' : 'Sono collegati più siti. Specifica analytics:diagnose ID, usando l’ID presente nell’URL del sito.');
            return self::FAILURE;
        }

        $property = $sites->sole()->googleAnalyticsProperty;
        $this->line('Test autenticato di un report con la sola metrica sessioni di ieri.');
        $this->line('La misura riuscita resta in cache per 10 minuti. Le pause vengono rispettate.');
        $started = null;

        try {
            $result = $reports->diagnose($property, function () use (&$started): void {
                $started = microtime(true);
                $this->line('Avvio di un nuovo tentativo autenticato.');
            });
            $this->info($started === null ? 'Report minimale riuscito. Misura riutilizzata dalla cache.' : 'Report minimale riuscito.');
            $this->line('Misurato il '.$result['measured_at']);
            $this->line('Tempo complessivo '.$result['elapsed_seconds'].' secondi.');
            $timings = $result['timings'];
        } catch (GoogleAnalyticsException $exception) {
            if ($started === null) {
                $this->warn('Test non eseguito. Lettura sospesa o altra richiesta già in corso.');
            } else {
                $this->line('Durata del nuovo tentativo PHP '.round(microtime(true) - $started, 3).' secondi.');
            }
            $this->error($exception->getMessage());
            $diagnostics = $reports->quotaDiagnostics($property);
            $error = $diagnostics['request_error'] ?? $diagnostics['error'] ?? [];
            if (isset($error['occurred_at'])) {
                $this->line('Ultimo errore registrato il '.$error['occurred_at']);
            }
            if (isset($error['retry_at'])) {
                $this->line('Nuovo tentativo consentito dalle '.$error['retry_at']);
            }
            $timings = $diagnostics['request_error']['timings'] ?? [];
            $this->printTimings($timings);
            return self::FAILURE;
        }

        $this->printTimings($timings);
        return self::SUCCESS;
    }

    private function printTimings(array $timings): void
    {
        foreach ($timings as $label => $seconds) {
            $this->line($label.' = '.$seconds.' s');
        }

        if ($timings !== []) {
            $this->line('Tempi cumulativi dall’inizio della comunicazione HTTP, in secondi.');
        }
    }
}
