<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['editorial_proposal_id', 'automatic', 'outcome', 'started_at', 'finished_at', 'x_post_id', 'message'])]
class XPublicationAttempt extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'automatic' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'message' => 'encrypted',
        ];
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(EditorialProposal::class, 'editorial_proposal_id');
    }
}
