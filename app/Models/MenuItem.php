<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    protected $fillable = [
        'menu_category_id',
        'code',
        'name',
        'description',
        'badge',
        'artwork',
        'sort_order',
        'is_active',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(MenuItemOption::class)->orderBy('sort_order');
    }
}
