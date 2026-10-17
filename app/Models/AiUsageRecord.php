<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ai_integration_id',
    'feature',
    'provider',
    'model',
    'response_id',
    'input_tokens',
    'output_tokens',
    'total_tokens',
    'occurred_at',
])]
class AiUsageRecord extends Model
{
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function aiIntegration(): BelongsTo
    {
        return $this->belongsTo(AiIntegration::class);
    }
}
