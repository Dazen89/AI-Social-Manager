<?php

namespace App\Services;

use App\Exceptions\XApiException;
use App\Models\EditorialProposal;
use App\Models\XConnection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class XPostMetricsCollector
{
    private const FIRST_MILESTONE_HOURS = 24;

    private const FINAL_MILESTONE_HOURS = 168;

    public function __construct(private readonly XService $x) {}

    public function runDue(): int
    {
        if (! app()->environment('production')) {
            return 0;
        }

        $connection = XConnection::query()->where('status', 'active')->first();

        if (! ($connection?->manual_publishing_enabled || $connection?->automatic_publishing_enabled)
            || ! in_array('tweet.read', $connection->scopes ?? [], true)) {
            return 0;
        }

        return (int) Cache::lock('editorial:x-metrics-collection', 120)
            ->get(fn (): int => $this->collect($connection));
    }

    private function collect(XConnection $connection): int
    {
        $now = now()->toImmutable();
        $weekCutoff = $now->subHours(self::FINAL_MILESTONE_HOURS);
        $proposals = EditorialProposal::query()->where('status', 'published')
            ->whereNotNull('x_post_id')
            ->where('published_at', '>', $now->subDays(30))
            ->where(fn (Builder $query) => $query->whereNull('x_metrics_attempted_at')
                ->orWhere('x_metrics_attempted_at', '<=', $now->subHours(6)))
            ->where(fn (Builder $due) => $due
                ->where(fn (Builder $first) => $first
                    ->where('published_at', '<=', $now->subHours(self::FIRST_MILESTONE_HOURS))
                    ->where('published_at', '>', $weekCutoff)
                    ->whereDoesntHave('metricSnapshots', fn (Builder $snapshot) => $snapshot
                        ->where('milestone_hours', self::FIRST_MILESTONE_HOURS)))
                ->orWhere(fn (Builder $final) => $final
                    ->where('published_at', '<=', $weekCutoff)
                    ->whereDoesntHave('metricSnapshots', fn (Builder $snapshot) => $snapshot
                        ->where('milestone_hours', self::FINAL_MILESTONE_HOURS))))
            ->withExists('metricSnapshots')
            ->orderBy('x_metrics_attempted_at')
            ->orderBy('metric_snapshots_exists')
            ->orderBy('published_at')
            ->orderBy('id')
            ->limit(XService::POST_LOOKUP_LIMIT)
            ->get();

        if ($proposals->isEmpty()) {
            return 0;
        }

        EditorialProposal::query()->whereKey($proposals->modelKeys())->update(['x_metrics_attempted_at' => $now]);

        try {
            $metricsByPost = $this->x->postMetrics($connection, $proposals->pluck('x_post_id')->unique()->values()->all());
        } catch (XApiException|ConnectionException $exception) {
            Log::warning('X post metrics unavailable', ['exception' => $exception::class, 'code' => $exception->getCode()]);

            return 0;
        }

        $collected = 0;
        $collectedAt = now();

        foreach ($proposals as $proposal) {
            $metrics = $metricsByPost[$proposal->x_post_id] ?? [];

            if ($metrics === []) {
                continue;
            }

            $milestone = $proposal->published_at <= $weekCutoff ? self::FINAL_MILESTONE_HOURS : self::FIRST_MILESTONE_HOURS;
            $snapshot = $proposal->metricSnapshots()->firstOrCreate(
                ['milestone_hours' => $milestone],
                ['metrics' => $metrics, 'collected_at' => $collectedAt],
            );
            $collected += (int) $snapshot->wasRecentlyCreated;
        }

        return $collected;
    }
}
