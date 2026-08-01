<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PasskeyCredential extends Model
{
    protected $fillable = [
        'user_id',
        'credential_id',
        'public_key',
        'sign_count',
        'transports',
        'aaguid',
        'device_name',
        'last_used_at',
        'active',
    ];

    protected $casts = [
        'sign_count' => 'integer',
        'transports' => 'array',
        'last_used_at' => 'datetime',
        'active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
