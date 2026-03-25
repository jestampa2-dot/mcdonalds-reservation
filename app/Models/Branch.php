<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $fillable = [
        'code',
        'name',
        'city',
        'supports',
        'concurrent_limit',
        'max_guests',
        'map_url',
        'inventory',
        'hosts',
        'is_active',
    ];

    protected $casts = [
        'supports' => 'array',
        'inventory' => 'array',
        'hosts' => 'array',
        'is_active' => 'boolean',
    ];

    public function supportedEventTypes(): BelongsToMany
    {
        return $this->belongsToMany(EventType::class, 'branch_event_type');
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(BranchInventoryItem::class);
    }

    public function hostsList(): HasMany
    {
        return $this->hasMany(BranchHost::class);
    }
}
