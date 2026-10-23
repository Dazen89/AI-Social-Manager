<?php

namespace App\Services;

use App\Exceptions\AiServiceException;
use App\Models\EditorialSchedule;
use App\Models\Site;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

class EditorialScheduleRunner
{
    public function __construct(
        private readonly EditorialContentImporter $importer,
        private readonly EditorialProposalService $proposals,
    ) {}

    public function runDue(): int
    {
        $ids = EditorialSchedule::query()
            ->where('enabled', true)
            ->whereHas('site', fn ($query) => $query->where('is_active', true))
            ->where('next_run_at', '<=', now())
            ->orderBy('next_run_at')
            ->limit(5)
            ->pluck('id');

        $processed = 0;

        foreach ($ids as $id) {
            if ($this->runOne((int) $id)) {
                $processed++;
            }
        }

        return $processed;
    }

    public function runNow(Site $site): bool
    {
        $schedule = $site->editorialSchedule()->firstOrCreate([], [
            'enabled' => false,
            'interval_hours' => 24,
            'start_time' => '09:00',
            'timezone' => (string) config('app.timezone'),
        ]);

        return $this->runOne($schedule->id, true);
    }

    private function runOne(int $id, bool $manual = false): bool
    {
        $claimed = EditorialSchedule::query()
            ->whereKey($id)
            ->when(! $manual, fn ($query) => $query->where('enabled', true)->where('next_run_at', '<=', now()))
            ->where(function ($query): void {
                $query->whereNull('running_started_at')->orWhere('running_started_at', '<', now()->subMinutes(10));
            })
            ->update(['running_started_at' => now(), 'last_attempt_at' => now()]);

        if ($claimed !== 1) {
            return false;
        }

        $schedule = EditorialSchedule::query()->with('site.editorialSources')->findOrFail($id);

        try {
            $sources = $schedule->site->editorialSources
                ->where('is_active', true)
                ->where('verification_status', 'verified');

            if ($sources->isEmpty()) {
                throw new AiServiceException('Nessuna fonte attiva e verificata per questo sito.');
            }

            $imported = 0;

            foreach ($sources as $source) {
                $result = $this->importer->import($source);
                $imported += $result['added'];
            }

            $generated = $this->proposals->generate($schedule->site);

            $schedule->update([
                'next_run_at' => $schedule->enabled ? $schedule->nextAfter(CarbonImmutable::now('UTC')) : null,
                'running_started_at' => null,
                'last_run_at' => now(),
                'last_error' => null,
                'consecutive_failures' => 0,
                'last_imported_count' => $imported,
                'last_proposal_count' => $generated,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Editorial schedule failed', ['schedule_id' => $id, 'exception' => $exception::class]);
            $failures = $schedule->consecutive_failures + 1;
            $paused = $failures >= 3;
            $schedule->update([
                'enabled' => $schedule->enabled && ! $paused,
                'next_run_at' => $schedule->enabled && ! $paused ? now()->addMinutes(15) : null,
                'running_started_at' => null,
                'consecutive_failures' => $failures,
                'last_error' => ($exception instanceof AiServiceException
                    ? $exception->getMessage()
                    : 'Controllo automatico non riuscito. Verifica la fonte e i servizi collegati.')
                    .($paused ? ' Piano sospeso dopo tre errori consecutivi; correggi il problema e riattivalo.' : ''),
            ]);
        }

        return true;
    }
}
