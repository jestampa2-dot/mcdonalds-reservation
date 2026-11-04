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
        'email_verified_at',
        'google_id',
        'google_link_pending',
        'phone',
        'sms_notifications_enabled',
        'birth_date',
        'gender',
        'address_line',
        'city',
        'province',
        'postal_code',
        'profile_photo_path',
        'google_avatar_url',
        'role',
        'branch_code',
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
        'google_link_pending' => 'boolean',
    ];

    public function getProfilePhotoUrlAttribute(): ?string
    {
        return $this->profile_photo_path
            ? asset('storage/'.$this->profile_photo_path)
            : $this->google_avatar_url;
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function assignedReservations()
    {
        return $this->hasMany(Reservation::class, 'assigned_staff_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_code', 'code');
    }

    public function sentReservationInvitations()
    {
        return $this->hasMany(ReservationInvitation::class, 'inviter_id');
    }

    public function receivedReservationInvitations()
    {
        return $this->hasMany(ReservationInvitation::class, 'invitee_user_id');
    }
}
