<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'site_id', 'editorial_content_id', 'ai_integration_id', 'platform', 'status',
    'post_text', 'rationale', 'proposed_for', 'source_snapshot', 'generated_at',
    'reviewed_by_user_id', 'reviewed_at',
    'manual_override',
    'x_post_id', 'published_at', 'delivery_attempted_at', 'delivery_error',
])]
class EditorialProposal extends Model
{
    protected function casts(): array
    {
        return [
            'proposed_for' => 'datetime',
            'source_snapshot' => 'encrypted:array',
            'generated_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'manual_override' => 'boolean',
            'published_at' => 'datetime',
            'delivery_attempted_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(EditorialContent::class, 'editorial_content_id');
    }

    public function metricSnapshots(): HasMany
    {
        return $this->hasMany(XPostMetricSnapshot::class);
    }
}
