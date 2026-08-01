<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecoveryCode extends Model
{
    protected $fillable = [
        'user_id',
        'code_hash',
        'used',
        'used_at',
    ];

    protected $casts = [
        'used' => 'boolean',
        'used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
