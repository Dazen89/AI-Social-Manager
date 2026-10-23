<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['site_id', 'ai_integration_id', 'posts_per_week', 'confidence', 'rationale', 'source_snapshot', 'generated_at'])]
class EditorialCadenceRecommendation extends Model
{
    protected function casts(): array
    {
        return [
            'posts_per_week' => 'integer',
            'source_snapshot' => 'encrypted:array',
            'generated_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
