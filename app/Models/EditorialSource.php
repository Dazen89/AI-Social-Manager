<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'site_id',
    'provider',
    'name',
    'base_url',
    'is_active',
    'verification_status',
    'verification_message',
    'last_checked_at',
    'last_verified_at',
])]
class EditorialSource extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_checked_at' => 'datetime',
            'last_verified_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function apiChaptersUrl(): string
    {
        return $this->base_url.'/api/reader/chapters/format/json';
    }
}
