<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'authorized_by_user_id',
    'access_token',
    'refresh_token',
    'token_expires_at',
    'scopes',
    'status',
    'last_error',
])]
#[Hidden(['access_token', 'refresh_token'])]
class GoogleSearchConsoleConnection extends Model
{
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'scopes' => 'array',
        ];
    }

    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by_user_id');
    }

    public function properties(): HasMany
    {
        return $this->hasMany(GoogleSearchConsoleProperty::class);
    }
}
