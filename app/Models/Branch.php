<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
