<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'provider',
    'configuration',
    'configured_by_user_id',
    'status',
    'configured_at',
    'last_tested_at',
    'last_error',
])]
#[Hidden(['configuration'])]
class IntegrationSetting extends Model
{
    public const GOOGLE_ANALYTICS = 'google_analytics';

    protected function casts(): array
    {
        return [
            'configuration' => 'encrypted:array',
            'configured_at' => 'datetime',
            'last_tested_at' => 'datetime',
        ];
    }

    public function configuredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'configured_by_user_id');
    }
}
