<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TwoFactorSetting extends Model
{
    protected $fillable = [
        'user_id',
        'enabled',
        'preferred_method',
        'required',
        'enabled_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'required' => 'boolean',
        'enabled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
