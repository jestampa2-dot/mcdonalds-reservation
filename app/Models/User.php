<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'sms_notifications_enabled',
        'birth_date',
        'gender',
        'address_line',
        'city',
        'province',
        'postal_code',
        'profile_photo_path',
        'role',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'birth_date' => 'date',
        'sms_notifications_enabled' => 'boolean',
    ];

    public function getProfilePhotoUrlAttribute(): ?string
    {
        return $this->profile_photo_path ? asset('storage/'.$this->profile_photo_path) : null;
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function assignedReservations()
    {
        return $this->hasMany(Reservation::class, 'assigned_staff_id');
    }
}
