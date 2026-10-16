<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'google_analytics_connection_id',
    'account_id',
    'account_name',
    'property_id',
    'property_name',
    'data_stream_id',
    'measurement_id',
    'last_verified_at',
])]
class GoogleAnalyticsProperty extends Model
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
        return $this->belongsTo(GoogleAnalyticsConnection::class, 'google_analytics_connection_id');
    }
}
