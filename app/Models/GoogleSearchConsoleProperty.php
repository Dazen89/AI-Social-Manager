<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'google_search_console_connection_id',
    'property_url',
    'property_type',
    'permission_level',
    'last_verified_at',
])]
class GoogleSearchConsoleProperty extends Model
{
    protected function casts(): array
    {
        return [
            'last_verified_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(GoogleSearchConsoleConnection::class, 'google_search_console_connection_id');
    }
}
