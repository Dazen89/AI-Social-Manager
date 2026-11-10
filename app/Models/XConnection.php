<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'authorized_by_user_id',
    'x_user_id',
    'username',
    'display_name',
    'profile_image_url',
    'access_token',
    'refresh_token',
    'token_expires_at',
    'scopes',
    'status',
    'manual_publishing_enabled',
    'automatic_publishing_enabled',
    'last_verified_at',
    'last_error',
])]
#[Hidden(['access_token', 'refresh_token'])]
class XConnection extends Model
{
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'scopes' => 'array',
            'manual_publishing_enabled' => 'boolean',
            'automatic_publishing_enabled' => 'boolean',
            'last_verified_at' => 'datetime',
        ];
    }

    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by_user_id');
    }

    public function hasWriteAccess(): bool
    {
        return $this->status === 'active' && in_array('tweet.write', $this->scopes ?? [], true);
    }

    public function canPublish(bool $automatic): bool
    {
        return ($automatic ? $this->automatic_publishing_enabled : $this->manual_publishing_enabled) && $this->hasWriteAccess();
    }
}
