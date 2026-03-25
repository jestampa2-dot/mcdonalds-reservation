<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchInventoryItem extends Model
{
    protected $fillable = [
        'branch_id',
        'item',
        'stock',
        'threshold',
        'sort_order',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
