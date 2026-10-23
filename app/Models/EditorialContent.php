<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'site_id', 'editorial_source_id', 'external_id', 'series_title', 'title',
    'public_url', 'released_at', 'eligibility_status', 'eligibility_confirmed_by_user_id', 'last_seen_at',
])]
class EditorialContent extends Model
{
    protected function casts(): array
    {
        return [
            'released_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(EditorialSource::class, 'editorial_source_id');
    }

    public function proposal(): HasOne
    {
        return $this->hasOne(EditorialProposal::class);
    }
}
