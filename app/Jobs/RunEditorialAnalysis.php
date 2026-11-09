<?php

namespace App\Jobs;

use App\Models\Site;
use App\Services\EditorialScheduleRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunEditorialAnalysis implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 240;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $siteId) {}

    public function uniqueId(): string
    {
        return (string) $this->siteId;
    }

    public function handle(EditorialScheduleRunner $runner): void
    {
        $site = Site::query()->find($this->siteId);

        if (! $site || ! $site->is_active) {
            return;
        }

        $runner->runNow($site);
    }
}
