<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsMessage extends Model
{
    protected $fillable = [
        'user_id',
        'reservation_id',
        'recipient',
        'event',
        'message',
        'status',
        'provider_batch_id',
        'error',
        'accepted_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];
}
