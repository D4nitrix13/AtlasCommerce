<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtpChallenge extends Model
{
    protected $fillable = [
        'user_id',
        'method',
        'purpose',
        'code_hash',
        'destination',
        'attempts',
        'max_attempts',
        'used',
        'expires_at',
        'used_at',
        'request_ip',
        'user_agent',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'used' => 'boolean',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return now()->greaterThan($this->expires_at);
    }

    public function hasAttemptsAvailable(): bool
    {
        return $this->attempts < $this->max_attempts;
    }
}
