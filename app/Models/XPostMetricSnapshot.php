<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['editorial_proposal_id', 'metrics', 'collected_at', 'milestone_hours'])]
class XPostMetricSnapshot extends Model
{
    protected function casts(): array
    {
        return ['metrics' => 'encrypted:array', 'collected_at' => 'datetime', 'milestone_hours' => 'integer'];
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(EditorialProposal::class, 'editorial_proposal_id');
    }
}
