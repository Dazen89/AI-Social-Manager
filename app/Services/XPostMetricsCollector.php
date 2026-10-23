<?php

namespace App\Services;

use App\Models\EditorialProposal;
use App\Models\XConnection;
use Illuminate\Support\Facades\Log;
use Throwable;

class XPostMetricsCollector
{
    public function __construct(private readonly XService $x) {}

    public function runDue(): int
    {
        if (! app()->environment('production') || ! config('services.x.auto_publish_allowed')) {
            return 0;
        }

        $connection = XConnection::query()->where('status', 'active')->first();

        if (! $connection || ! in_array('tweet.read', $connection->scopes ?? [], true)) {
            return 0;
        }

        $proposals = EditorialProposal::query()->where('status', 'published')
            ->whereNotNull('x_post_id')->where('published_at', '>=', now()->subDays(30))
            ->whereDoesntHave('metricSnapshots', fn ($query) => $query->where('collected_at', '>', now()->subHours(6)))
            ->orderBy('published_at')->limit(10)->get();
        $collected = 0;

        foreach ($proposals as $proposal) {
            try {
                $metrics = $this->x->postPublicMetrics($connection, $proposal->x_post_id);

                if ($metrics === []) {
                    continue;
                }

                $proposal->metricSnapshots()->create(['metrics' => $metrics, 'collected_at' => now()]);
                $collected++;
            } catch (Throwable $exception) {
                Log::warning('X post metrics unavailable', ['proposal_id' => $proposal->id, 'exception' => $exception::class]);
            }
        }

        return $collected;
    }
}
