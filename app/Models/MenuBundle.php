<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuBundle extends Model
{
    protected $fillable = [
        'code',
        'name',
        'price',
        'prep_label',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
