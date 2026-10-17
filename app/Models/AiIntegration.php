<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'provider',
    'model',
    'api_key',
    'active_marker',
    'configured_by_user_id',
    'status',
    'configured_at',
    'last_tested_at',
    'last_error',
])]
#[Hidden(['api_key'])]
class AiIntegration extends Model
{
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'configured_at' => 'datetime',
            'last_tested_at' => 'datetime',
        ];
    }

    public function configuredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'configured_by_user_id');
    }

    public function usageRecords(): HasMany
    {
        return $this->hasMany(AiUsageRecord::class);
    }

    public function analyticsInsightReports(): HasMany
    {
        return $this->hasMany(AnalyticsInsightReport::class);
    }

    protected function isActive(): Attribute
    {
        return Attribute::get(fn (): bool => $this->active_marker === 'active');
    }
}
