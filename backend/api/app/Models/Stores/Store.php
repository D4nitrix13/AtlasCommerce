<?php

namespace App\Models\Stores;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'logo',
        'email',
        'phone',
        'address',
        'primary_currency',
        'maintenance_mode',
        'is_active',
    ];

    protected $casts = [
        'maintenance_mode' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function domains(): HasMany
    {
        return $this->hasMany(StoreDomain::class);
    }

    public function socialLinks(): HasMany
    {
        return $this->hasMany(StoreSocialLink::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(ProductStore::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(UserStore::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(CustomerStore::class);
    }

    public function salesChannels(): HasMany
    {
        return $this->hasMany(SalesChannel::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(StoreSetting::class);
    }

    public function primaryDomain(): ?StoreDomain
    {
        return $this->domains()
            ->where('is_primary', true)
            ->where('is_active', true)
            ->first();
    }
}
