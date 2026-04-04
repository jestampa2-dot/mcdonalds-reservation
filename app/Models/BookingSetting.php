<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingSetting extends Model
{
    protected $fillable = [
        'opening_hour',
        'closing_hour',
        'default_duration_hours',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
