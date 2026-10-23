<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'site_id', 'enabled', 'interval_hours', 'start_time', 'timezone', 'next_run_at',
    'running_started_at', 'last_attempt_at', 'last_run_at', 'last_error',
    'last_imported_count', 'last_proposal_count', 'consecutive_failures',
    'x_autopublish_enabled',
])]
class EditorialSchedule extends Model
{
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'x_autopublish_enabled' => 'boolean',
            'interval_hours' => 'integer',
            'consecutive_failures' => 'integer',
            'next_run_at' => 'datetime',
            'running_started_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'last_run_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function nextAfter(CarbonImmutable $after): CarbonImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', $this->start_time));
        $local = $after->setTimezone($this->timezone);
        $next = $local->startOfDay()->setTime($hour, $minute);

        while ($next <= $local) {
            $next = $next->addHours($this->interval_hours);
        }

        return $next->setTimezone('UTC');
    }
}
