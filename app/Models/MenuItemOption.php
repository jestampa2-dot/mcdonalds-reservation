<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItemOption extends Model
{
    protected $fillable = [
        'menu_item_id',
        'code',
        'label',
        'price',
        'prep_label',
        'sort_order',
        'is_active',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}
