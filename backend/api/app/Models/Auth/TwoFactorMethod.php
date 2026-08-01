<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TwoFactorMethod extends Model
{
    protected $fillable = [
        'user_id',
        'method',
        'display_name',
        'destination',
        'encrypted_secret',
        'active',
        'verified',
        'verified_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
