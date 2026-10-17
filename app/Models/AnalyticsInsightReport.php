<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'site_id',
    'ai_integration_id',
    'range_days',
    'start_date',
    'end_date',
    'source_hash',
    'provider',
    'model',
    'source_snapshot',
    'analysis',
    'input_tokens',
    'output_tokens',
    'total_tokens',
    'generated_at',
])]
#[Hidden(['source_snapshot'])]
class AnalyticsInsightReport extends Model
{
    protected function casts(): array
    {
        return [
            'source_snapshot' => 'encrypted:array',
            'analysis' => 'encrypted:array',
            'start_date' => 'date',
            'end_date' => 'date',
            'generated_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function aiIntegration(): BelongsTo
    {
        return $this->belongsTo(AiIntegration::class);
    }
}
