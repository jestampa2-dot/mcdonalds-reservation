<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    protected $fillable = [
        'user_id',
        'assigned_staff_id',
        'name',
        'email',
        'phone',
        'booking_reference',
        'reservation_type',
        'package_name',
        'package_code',
        'room_choice',
        'food_package',
        'beverage_package',
        'event_materials',
        'branch',
        'branch_code',
        'event_date',
        'event_time',
        'duration_hours',
        'menu_bundles',
        'add_ons',
        'manual_menu_items',
        'service_adjustments',
        'payment_proof_path',
        'guests',
        'total_amount',
        'check_in_code',
        'checked_in_at',
        'checked_in_by',
        'notes',
        'status',
        'service_status',
    ];

    protected $casts = [
        'menu_bundles' => 'array',
        'add_ons' => 'array',
        'manual_menu_items' => 'array',
        'service_adjustments' => 'array',
        'checked_in_at' => 'datetime',
        'event_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }
}
