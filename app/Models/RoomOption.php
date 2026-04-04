<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomOption extends Model
{
    protected $fillable = [
        'code',
        'label',
        'description',
        'preferred_event_type',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
