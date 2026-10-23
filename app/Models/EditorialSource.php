<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    'promotion_authorized_at',
    'promotion_authorized_by_user_id',
])]
class EditorialSource extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_checked_at' => 'datetime',
            'last_verified_at' => 'datetime',
            'promotion_authorized_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function contents(): HasMany
    {
        return $this->hasMany(EditorialContent::class);
    }

    public function apiChaptersUrl(): string
    {
        return $this->base_url.'/api/reader/chapters/format/json';
    }

    public function containsPublicUrl(string $url): bool
    {
        $base = parse_url($this->base_url);
        $parts = parse_url($url);

        if (! is_array($base) || ! is_array($parts)
            || ! isset($parts['scheme'], $parts['host'], $parts['path'])
            || strtolower($parts['scheme']) !== strtolower($base['scheme'] ?? '')
            || strtolower($parts['host']) !== strtolower($base['host'] ?? '')
            || ($parts['port'] ?? null) !== ($base['port'] ?? null)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return false;
        }

        $basePath = rtrim((string) ($base['path'] ?? ''), '/');

        return $basePath === '' || str_starts_with($parts['path'], $basePath.'/');
    }
}
