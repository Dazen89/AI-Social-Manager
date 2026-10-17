<?php

namespace App\Models;

use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'base_url', 'host', 'is_active'])]
class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function googleAnalyticsProperty(): HasOne
    {
        return $this->hasOne(GoogleAnalyticsProperty::class);
    }

    public function analyticsInsightReports(): HasMany
    {
        return $this->hasMany(AnalyticsInsightReport::class);
    }
}
