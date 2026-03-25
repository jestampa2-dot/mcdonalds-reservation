<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingSetting extends Model
{
    protected $fillable = [
        'weekend_multiplier',
        'holiday_multiplier',
        'extension_hourly_rate',
        'holidays',
        'is_active',
    ];

    protected $casts = [
        'weekend_multiplier' => 'decimal:2',
        'holiday_multiplier' => 'decimal:2',
        'extension_hourly_rate' => 'decimal:2',
        'holidays' => 'array',
        'is_active' => 'boolean',
    ];
}
